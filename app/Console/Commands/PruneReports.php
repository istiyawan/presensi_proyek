<?php

namespace App\Console\Commands;

use App\Models\ReportJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('reports:prune')]
#[Description('Hapus berkas laporan lama untuk menghemat ruang disk')]
class PruneReports extends Command
{
    public function handle(): int
    {
        $count = 0;
        ReportJob::query()
            ->where('created_at', '<', now()->subDays(config('presensi.report_retention_days')))
            ->each(function (ReportJob $job) use (&$count) {
                if ($job->file_path) {
                    Storage::delete($job->file_path);
                }
                $job->delete();
                $count++;
            });

        $this->info("{$count} laporan lama dihapus.");

        return self::SUCCESS;
    }
}
