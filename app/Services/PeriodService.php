<?php

namespace App\Services;

use App\Models\Project;
use Carbon\CarbonImmutable;

/**
 * Periode laporan proyek. Periode hanya filter laporan (bukan atribut data
 * presensi), sehingga periode yang tumpang tindih (25 → 26) tidak membuat
 * data ganda.
 *
 * Bulan ke-1 = periode pertama yang dimulai pada/sesudah contract_start_date.
 */
class PeriodService
{
    public function __construct(private SettingService $settings) {}

    /**
     * @return array{index: int, label: string, start: CarbonImmutable, end: CarbonImmutable}
     */
    public function period(Project $project, int $index): array
    {
        $first = $this->firstPeriodStart($project);
        $start = $this->dayInMonth($first->addMonthsNoOverflow($index - 1), $this->startDay($project));

        return $this->make($project, $index, $start);
    }

    /**
     * Daftar periode dari Bulan ke-1 sampai periode yang memuat $until.
     *
     * @return list<array{index: int, label: string, start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function periods(Project $project, ?CarbonImmutable $until = null): array
    {
        $until ??= CarbonImmutable::now($project->timezone);
        $periods = [];

        for ($i = 1; $i <= 600; $i++) {
            $p = $this->period($project, $i);
            if ($p['start']->gt($until)) {
                break;
            }
            $periods[] = $p;
        }

        return $periods;
    }

    /** Periode terbaru yang memuat tanggal tsb (untuk tanggal tumpang tindih, ambil yang lebih baru). */
    public function current(Project $project, ?CarbonImmutable $date = null): ?array
    {
        $date ??= CarbonImmutable::now($project->timezone);

        foreach (array_reverse($this->periods($project, $date)) as $p) {
            if ($date->betweenIncluded($p['start'], $p['end'])) {
                return $p;
            }
        }

        return null;
    }

    private function make(Project $project, int $index, CarbonImmutable $start): array
    {
        $s = $this->settings->project($project);
        $endMonth = $s['period_end_next_month'] ? $start->addMonthsNoOverflow(1) : $start;
        $end = $this->dayInMonth($endMonth, (int) $s['period_end_day']);

        return [
            'index' => $index,
            'label' => "Bulan ke-{$index}",
            'start' => $start,
            'end' => $end,
        ];
    }

    private function firstPeriodStart(Project $project): CarbonImmutable
    {
        $contractStart = CarbonImmutable::parse(
            $project->contract_start_date ?? $project->created_at ?? 'now',
            $project->timezone,
        )->startOfDay();

        $candidate = $this->dayInMonth($contractStart, $this->startDay($project));

        return $candidate->lt($contractStart)
            ? $this->dayInMonth($contractStart->addMonthsNoOverflow(1), $this->startDay($project))
            : $candidate;
    }

    private function startDay(Project $project): int
    {
        return (int) $this->settings->project($project, 'period_start_day');
    }

    /** Tanggal ke-$day pada bulan $month; dipotong ke akhir bulan bila tidak ada (mis. 31 Feb). */
    private function dayInMonth(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->startOfMonth()->setDay(min($day, $month->daysInMonth))->startOfDay();
    }
}
