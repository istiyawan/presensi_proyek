<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\DailyCloseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:flag-missing-checkout {--project= : ID proyek}')]
#[Description('Tandai presensi yang melewati batas jam kerja tanpa check-out')]
class FlagMissingCheckout extends Command
{
    public function handle(DailyCloseService $service): int
    {
        Project::query()
            ->where('is_active', true)
            ->when($this->option('project'), fn ($q, $id) => $q->whereKey($id))
            ->each(function (Project $project) use ($service) {
                $count = $service->flagMissingCheckouts($project);
                $this->info("[{$project->code}] lupa check-out ditandai: {$count}");
            });

        return self::SUCCESS;
    }
}
