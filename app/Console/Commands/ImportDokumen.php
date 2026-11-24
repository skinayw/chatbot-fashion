<?php

namespace App\Console\Commands;

use App\Services\DocumentService;
use Illuminate\Console\Command;

class ImportDokumen extends Command
{
    protected $signature = 'dokumen:impor {file : Nama file di storage/app/dokumenchat atau path lengkap}';
    protected $description = 'Baca PDF/Word lalu simpan potongan teksnya ke storage/app/knowledge';

    public function handle(DocumentService $dokumen): int
    {
        $input = $this->argument('file');
        $path = file_exists($input) ? $input : storage_path('app/dokumenchat/' . $input);

        if (!file_exists($path)) {
            $this->error("File tidak ditemukan: $path");
            return self::FAILURE;
        }

        try {
            $teks = $dokumen->ekstrakTeks($path);
        } catch (\Throwable $e) {
            $this->error('Gagal membaca: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (mb_strlen(trim($teks)) < 20) {
            $this->error('Teks kosong. Kemungkinan PDF hasil scan atau foto.');
            return self::FAILURE;
        }

        $potongan = $dokumen->potong($teks);
        $dokumen->simpan(basename($path), $potongan);

        $this->info(count($potongan) . ' potongan tersimpan di storage/app/knowledge/');
        $this->line('Potongan pertama: ' . mb_substr($potongan[0], 0, 150) . '...');

        return self::SUCCESS;
    }
}