<?php

namespace App\Console\Commands;

use App\Services\DocumentSearchService;
use Illuminate\Console\Command;

class CariDokumen extends Command
{
    protected $signature = 'dokumen:cari {pertanyaan : Pertanyaan atau kata kunci, pakai tanda kutip}';
    protected $description = 'Tes pencarian potongan dokumen di storage/app/knowledge';

    public function handle(DocumentSearchService $pencarian): int
    {
        $pertanyaan = $this->argument('pertanyaan');
        $hasil = $pencarian->cari($pertanyaan);

        if (empty($hasil)) {
            $this->warn('Tidak ada potongan yang cocok.');
            return self::SUCCESS;
        }

        $this->info('Ditemukan ' . count($hasil) . " potongan untuk: \"$pertanyaan\"");

        foreach ($hasil as $i => $h) {
            $this->newLine();
            $this->line('#' . ($i + 1) . ' | skor ' . $h['skor'] . ' | ' . $h['file']);
            $this->line($h['teks']);
        }

        return self::SUCCESS;
    }
}