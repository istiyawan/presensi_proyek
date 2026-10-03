<?php

namespace App\Console\Commands;

use App\Models\Holiday;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('holidays:import {year : Tahun, mis. 2026}')]
#[Description('Impor libur nasional & cuti bersama dari database/data/holidays/{year}.json')]
class ImportHolidays extends Command
{
    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $path = database_path("data/holidays/{$year}.json");

        if (! is_file($path)) {
            $this->error("File {$path} tidak ditemukan.");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $count = 0;

        foreach ($data['holidays'] as $h) {
            Holiday::updateOrCreate(
                ['date' => $h['date'], 'project_id' => null, 'type' => $h['type']],
                ['name' => $h['name']],
            );
            $count++;
        }

        $this->info("{$count} hari libur {$year} diimpor.");
        if (! empty($data['_catatan'])) {
            $this->warn($data['_catatan']);
        }

        return self::SUCCESS;
    }
}
