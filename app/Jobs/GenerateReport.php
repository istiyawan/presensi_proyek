<?php

namespace App\Jobs;

use App\Models\ReportJob;
use App\Services\ReportGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReport implements ShouldQueue
{
    use Queueable;

    /** PDF gabungan banyak karyawan + foto bisa memakan waktu. */
    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public ReportJob $report) {}

    public function handle(ReportGenerator $generator): void
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $this->report->update(['status' => 'processing', 'started_at' => now(), 'error' => null]);

        $path = $generator->generate($this->report);

        $this->report->update([
            'status' => 'done',
            'file_path' => $path,
            'file_size' => Storage::size($path),
            'finished_at' => now(),
        ]);
    }

    public function failed(?Throwable $e): void
    {
        $this->report->update([
            'status' => 'failed',
            'error' => $e ? mb_substr($e->getMessage(), 0, 1000) : 'Gagal membuat laporan.',
            'finished_at' => now(),
        ]);
    }
}
