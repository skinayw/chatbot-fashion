<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class DocumentService
{
    // 1. Baca teks dari file sesuai ekstensinya
    public function ekstrakTeks(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf'       => (new Parser())->parseFile($path)->getText(),
            'docx'      => $this->bacaWord($path),
            'txt', 'md' => file_get_contents($path),
            default     => throw new \InvalidArgumentException("Format .$ext belum didukung"),
        };
    }

    private function bacaWord(string $path): string
    {
        $teks = '';
        foreach (IOFactory::load($path)->getSections() as $section) {
            $teks .= $this->ambilTeks($section->getElements());
        }
        return $teks;
    }

    // Word menyimpan isi dalam elemen bersarang, jadi dibaca satu per satu
    private function ambilTeks(array $elements, bool $dalamParagraf = false): string
    {
        $hasil = '';
        foreach ($elements as $el) {
            if ($el instanceof Table) {
                foreach ($el->getRows() as $row) {
                    $sel = [];
                    foreach ($row->getCells() as $cell) {
                        $sel[] = trim(str_replace("\n", ' ', $this->ambilTeks($cell->getElements())));
                    }
                    $hasil .= implode(' | ', $sel) . "\n";
                }
            } elseif ($el instanceof TextRun) {
                $hasil .= $this->ambilTeks($el->getElements(), true) . "\n";
            } elseif ($el instanceof Text) {
                $hasil .= $el->getText() . ($dalamParagraf ? '' : "\n");
            } elseif ($el instanceof ListItem) {
                $hasil .= '- ' . $el->getText() . "\n";
            } elseif (method_exists($el, 'getText') && is_string($el->getText())) {
                $hasil .= $el->getText() . "\n";
            }
        }
        return $hasil;
    }

    // 2. Potong teks panjang jadi potongan kecil (~800 karakter)
    public function potong(string $teks, int $ukuran = 800): array
    {
        $teks = preg_replace('/[ \t]+/', ' ', $teks);
        $baris = array_filter(array_map('trim', explode("\n", $teks)), fn ($b) => $b !== '');

        $potongan = [];
        $sekarang = '';

        foreach ($baris as $b) {
            // baris yang terlalu panjang dipecah dulu
            foreach (mb_str_split($b, $ukuran) as $bagian) {
                if ($sekarang !== '' && mb_strlen($sekarang) + mb_strlen($bagian) + 1 > $ukuran) {
                    $potongan[] = $sekarang;
                    $sekarang = '';
                }
                $sekarang .= ($sekarang === '' ? '' : ' ') . $bagian;
            }
        }
        if ($sekarang !== '') {
            $potongan[] = $sekarang;
        }
        return $potongan;
    }

    // 3. Simpan sebagai JSON di storage/app/knowledge/
    public function simpan(string $namaFile, array $potongan): void
    {
        $folder = storage_path('app/knowledge');
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $data = [
            'file'     => $namaFile,
            'diimpor'  => now()->toDateTimeString(),
            'potongan' => $potongan,
        ];

        file_put_contents(
            $folder . '/' . pathinfo($namaFile, PATHINFO_FILENAME) . '.json',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }
}