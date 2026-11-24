<?php

namespace App\Services;

use App\Models\Product;

class ProductSearchService
{
    private array $synonyms = [
        'tee' => 'kaos', 't-shirt' => 'kaos', 'tshirt' => 'kaos', 'kaus' => 'kaos',
        'chino' => 'celana', 'jeans' => 'celana', 'pants' => 'celana',
        'jaket' => 'outer', 'jacket' => 'outer', 'cardigan' => 'outer', 'blazer' => 'outer',
        'sneakers' => 'sepatu', 'shoes' => 'sepatu',
        'ransel' => 'tas', 'backpack' => 'tas',
        'gamis' => 'dress', 'terusan' => 'dress',
    ];

    public function search(string $message, int $limit = 8)
    {
        $words = $this->keywords($message);
        $max = $this->maxPrice($message);

        if (empty($words) && $max === null) {
            return collect();
        }

        $query = Product::query()
            ->where('is_active', true)
            ->with(['category', 'variants']);

        if (! empty($words)) {
            $query->where(function ($q) use ($words) {
                foreach ($words as $w) {
                    $q->orWhere('name', 'like', "%{$w}%")
                      ->orWhere('description', 'like', "%{$w}%")
                      ->orWhere('material', 'like', "%{$w}%")
                      ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$w}%"))
                      ->orWhereHas('variants', fn ($v) => $v->where('color', 'like', "%{$w}%"));
                }
            });
        }

        if ($max !== null) {
            $query->where('price', '<=', $max);
        }

        $products = $query->limit(50)->get();

        if (empty($words)) {
            return $products->take($limit)->values();
        }

        $scored = $products->map(fn ($p) => ['product' => $p, 'score' => $this->score($p, $words)]);
        $top = $scored->max('score') ?? 0;
        $min = max(1, (int) ceil($top / 2));  

        return $scored
            ->where('score', '>=', $min)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('product')
            ->values();
    }

    private function keywords(string $message): array
    {
        $stop = ['ada', 'apa', 'yang', 'dan', 'atau', 'dari', 'untuk', 'berapa', 'harga',
                 'harganya', 'ukuran', 'warna', 'kak', 'mau', 'cari', 'dong', 'nggak', 'gak',
                 'bawah', 'dibawah', 'ribu', 'juta', 'maksimal', 'kurang', 'under', 'stok',
                 'masih', 'tersedia', 'bisa', 'tolong', 'baju', 'pakaian', 'produk', 'barang', 'fashion', 'item', 'hari', 'ini', 'itu', 'bagaimana', 'gimana', 'kapan', 'dimana', 'siapa', 'kenapa', 'lama', 'kalau', 'saya', 'aku', 'kamu',];

        $words = preg_split('/[^a-z0-9\-]+/i', mb_strtolower($message), -1, PREG_SPLIT_NO_EMPTY);

        $words = array_filter(
            $words,
            fn ($w) => mb_strlen($w) > 2 && ! ctype_digit($w) && ! in_array($w, $stop)
        );

        $extra = [];
        foreach ($words as $w) {
            if (isset($this->synonyms[$w])) {
                $extra[] = $this->synonyms[$w];
            }
        }

        return array_values(array_unique(array_merge($words, $extra)));
    }

   private function maxPrice(string $message): ?int
    {
        $pattern = '/(?:di\s?bawah|kurang dari|maksimal|maks|under)\s*(?:rp\.?\s*)?([\d.,]+)\s*(ribu|rb|k|juta|jt)?/i';

        if (! preg_match($pattern, $message, $m)) {
            return null;
        }

        $unit = strtolower($m[2] ?? '');
        $rawNumber = str_replace(['.', ','], ['', '.'], $m[1]);
        $n = (float) $rawNumber;

        if ($unit === '') {
            return (int) $n;
        }

        $multiplier = in_array($unit, ['juta', 'jt']) ? 1000000 : 1000;

        // PERBAIKAN 2: Gunakan perkalian (*) secara tepat
        return (int) round($n * $multiplier);
    }

    private function score(Product $p, array $words): int
    {
        $name  = mb_strtolower($p->name . ' ' . $p->category->name);
        $other = mb_strtolower($p->description . ' ' . $p->material . ' ' . $p->variants->pluck('color')->implode(' '));

        $score = 0;
        foreach ($words as $w) {
            if (str_contains($name, $w)) {
                $score += 2;     
            } elseif (str_contains($other, $w)) {
                $score += 1;     
            }
        }

        return $score;
    }
}   