<?php

namespace App\Services;

class DocumentSearchService
{
    // Kata umum yang diabaikan, karena tidak membantu mencari
    private array $stopwords = [
        'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'apa', 'ada', 'bisa', 'berapa',
        'bagaimana', 'gimana', 'kak', 'ya', 'dengan', 'atau', 'itu', 'ini', 'saya',
        'aku', 'mau', 'kalau', 'jika', 'tidak', 'apakah', 'boleh', 'minta', 'tolong', 
        'cara', 'membuat', 'buat', 'tentang', 'info', 'informasi',
    ];

    // Kata yang sering dipakai pelanggan dan padanannya di dokumen (belum ada stemming)
    private array $sinonim = [
        'tukar'       => ['penukaran', 'menukar', 'ditukar'],
        'kirim'       => ['pengiriman', 'dikirim', 'ekspedisi'],
        'ongkir'      => ['biaya pengiriman'],
        'retur'       => ['pengembalian', 'penukaran'],
        'cuci'        => ['dicuci'],
        'buka'        => ['jam layanan'],
        'tutup'       => ['jam layanan'],
        'operasional' => ['jam layanan'],
        'admin'       => ['layanan pelanggan'],
        'kontak'      => ['whatsapp', 'email'],
        'hubungi'     => ['whatsapp', 'email'],
    ];

    // Cari potongan paling relevan dari semua file JSON di storage/app/knowledge
    public function cari(string $pertanyaan, int $batas = 3): array
    {
        $kataKunci = $this->ambilKataKunci($pertanyaan);
        if (empty($kataKunci)) {
            return [];
        }

        $hasil = [];

        foreach (glob(storage_path('app/knowledge/*.json')) as $fileJson) {
            $data = json_decode(file_get_contents($fileJson), true);
            if (empty($data['potongan'])) {
                continue;
            }

            foreach ($data['potongan'] as $teks) {
                [$skor, $cocok] = $this->hitungSkor(mb_strtolower($teks), $kataKunci);

                // Pertanyaan pendek (1–2 kata kunci) cukup 1 yang cocok; pertanyaan panjang minimal 2
                if ($cocok >= (count($kataKunci) >= 3 ? 2 : 1)) {
                    $hasil[] = ['file' => $data['file'] ?? basename($fileJson), 'skor' => $skor, 'teks' => $teks];
                }
            }
        }

        // Urutkan dari skor tertinggi, ambil beberapa teratas
        usort($hasil, fn ($a, $b) => $b['skor'] <=> $a['skor']);

        return array_slice($hasil, 0, $batas);
    }

    // Pecah pertanyaan jadi kata-kata penting
    private function ambilKataKunci(string $pertanyaan): array
    {
        $kata = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($pertanyaan), -1, PREG_SPLIT_NO_EMPTY);

        $kata = array_filter($kata, fn ($k) => mb_strlen($k) >= 3 && !in_array($k, $this->stopwords));

        return array_values(array_unique($kata));
    }

    private function hitungSkor(string $teks, array $kataKunci): array
    {
        $skor = 0;
        $cocok = 0;
        foreach ($kataKunci as $kata) {
            $jumlah = 0;
            foreach ($this->variasi($kata) as $bentuk) {
                $jumlah += substr_count($teks, $bentuk);
            }
            if ($jumlah > 0) {
                $cocok++;
                $skor += 2 + min($jumlah, 3);
            }
        }
        return [$skor, $cocok];
    }

    // Kata kunci beserta bentuk lain yang artinya sama
    private function variasi(string $kata): array
    {
        $hasil = [$kata];
        foreach ($this->sinonim as $akar => $daftar) {
            if (str_contains($kata, $akar)) {
                $hasil = array_merge($hasil, $daftar);
            }
        }
        return $hasil;
    }
}