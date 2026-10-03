<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\GenerateReport;
use App\Models\Employee;
use App\Models\ReportJob;
use App\Services\PeriodService;
use App\Services\ReportService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Laporan dibuat sebagai ReportJob. Laporan kecil diproses langsung; laporan besar
 * (banyak karyawan × hari) masuk antrean agar tidak melewati batas waktu shared hosting.
 */
class ReportController extends AdminController
{
    public function index(PeriodService $periods, SettingService $settings): View
    {
        $project = $this->project();

        return view('reports.index', [
            'page' => 'reports',
            'title' => 'Laporan Absensi',
            'periods' => array_reverse($periods->periods($project)),
            'current' => $periods->current($project),
            'employees' => $project->employees()->with('position')->orderBy('full_name')->get(),
            'showPhoto' => (bool) $settings->project($project, 'report_show_photo'),
        ]);
    }

    public function history(): JsonResponse
    {
        $project = $this->project();
        $tz = $project->timezone;

        $jobs = ReportJob::query()
            ->with('user')
            ->where('project_id', $project->id)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ReportJob $j) => [
                'id' => $j->id,
                'title' => $j->title,
                'type' => ReportJob::TYPES[$j->type],
                'format' => $j->format,
                'status' => $j->status,
                'error' => $j->error,
                'by' => $j->user?->name,
                'created' => $j->created_at->setTimezone($tz)->translatedFormat('j M Y H:i'),
                'ago' => $j->created_at->diffForHumans(),
                'size' => $j->file_size ? number_format($j->file_size / 1024, 0, ',', '.').' KB' : null,
                'duration' => $j->started_at && $j->finished_at ? $j->started_at->diffInSeconds($j->finished_at, true) : null,
                'view_url' => $j->status === 'done' && $j->format === 'pdf' ? route('reports.download', [$j, 'inline' => 1]) : null,
                'download_url' => $j->status === 'done' ? route('reports.download', $j) : null,
            ]);

        return response()->json($jobs);
    }

    public function store(Request $request, ReportService $reports): JsonResponse
    {
        $project = $this->project();

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReportJob::TYPES))],
            'format' => ['required', 'in:pdf,xlsx'],
            'range_mode' => ['required', 'in:period,custom'],
            'period' => ['nullable', 'required_if:range_mode,period', 'integer', 'min:1'],
            'from' => ['nullable', 'required_if:range_mode,custom', 'date'],
            'to' => ['nullable', 'required_if:range_mode,custom', 'date', 'after_or_equal:from'],
            'employee_id' => ['nullable', 'required_if:type,individual', Rule::exists('project_employee', 'employee_id')->where('project_id', $project->id)],
            'photos' => ['nullable', 'boolean'],
        ], [
            'employee_id.required_if' => 'Pilih karyawan untuk laporan individual.',
            'period.required_if' => 'Pilih periode.',
            'from.required_if' => 'Pilih rentang tanggal.',
            'to.required_if' => 'Pilih rentang tanggal.',
        ]);

        if ($data['format'] === 'xlsx' && $data['type'] !== 'recap') {
            return $this->failed('Format Excel hanya tersedia untuk laporan rekap.');
        }

        $params = $data['range_mode'] === 'period'
            ? ['period' => (int) $data['period']]
            : ['from' => $data['from'], 'to' => $data['to']];
        $range = $reports->range($project, $params);

        if ($range['from']->diffInDays($range['to']) > 92) {
            return $this->failed('Rentang laporan maksimal 93 hari.');
        }

        $params += array_filter([
            'employee_id' => $data['type'] === 'individual' ? (int) $data['employee_id'] : null,
            'photos' => $data['type'] !== 'recap' ? $request->boolean('photos') : null,
        ], fn ($v) => $v !== null);

        $job = ReportJob::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'type' => $data['type'],
            'format' => $data['format'],
            'params' => $params,
            'title' => $this->title($project->code, $data['type'], $range, $params),
        ]);

        // Perkiraan beban: karyawan × hari, foto dihitung dua kali lipat (±24 dtk untuk 270 baris berfoto)
        $employees = $data['type'] === 'individual' ? 1 : $reports->assignments($project, $range['from'], $range['to'])->count();
        $rows = $employees * ($range['from']->diffInDays($range['to']) + 1) * (! empty($params['photos']) ? 2 : 1);
        $queued = $data['type'] === 'combined' && $rows > (int) config('presensi.report_sync_max_rows');

        if ($queued) {
            GenerateReport::dispatch($job);

            return $this->saved('Laporan besar sedang diproses di latar belakang. Status diperbarui otomatis.', ['queued' => true]);
        }

        try {
            @set_time_limit(180);
            GenerateReport::dispatchSync($job);
        } catch (Throwable $e) {
            report($e);
            $job->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);

            return $this->failed('Gagal membuat laporan: '.$e->getMessage(), 500);
        }

        $job->refresh();

        return $this->saved('Laporan siap.', [
            'view_url' => $job->format === 'pdf' ? route('reports.download', [$job, 'inline' => 1]) : null,
            'download_url' => route('reports.download', $job),
        ]);
    }

    public function download(Request $request, ReportJob $report): StreamedResponse
    {
        abort_unless($report->project_id === $this->project()->id, 404);
        abort_unless($report->status === 'done' && $report->file_path && Storage::exists($report->file_path), 404, 'Berkas laporan tidak tersedia.');

        return $request->boolean('inline')
            ? Storage::response($report->file_path, $report->filename, ['Content-Type' => 'application/pdf'])
            : Storage::download($report->file_path, $report->filename);
    }

    public function destroy(ReportJob $report): JsonResponse
    {
        abort_unless($report->project_id === $this->project()->id, 404);

        if ($report->file_path) {
            Storage::delete($report->file_path);
        }
        $report->delete();

        return $this->saved('Laporan dihapus.');
    }

    private function title(string $code, string $type, array $range, array $params): string
    {
        $prefix = $type === 'recap' ? 'Rekap Absensi' : 'Laporan Absensi';
        $period = $range['label'] ?? $range['from']->format('d-m-Y').' sd '.$range['to']->format('d-m-Y');
        $who = match ($type) {
            'individual' => Employee::find($params['employee_id'])?->full_name,
            'combined' => 'Semua Karyawan',
            default => null,
        };

        return implode(' - ', array_filter([$prefix, $code, $period, $who]));
    }
}
