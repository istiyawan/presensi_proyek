<?php

namespace Tests\Feature\Admin;

use App\Jobs\GenerateReport;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\ReportJob;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\BuildsProjects;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-10-05 10:00:00');
        $this->project = $this->makeProject();
    }

    private function admin(): User
    {
        $user = User::create(['name' => 'Admin', 'username' => 'admin', 'password' => 'password']);
        $user->assignRole(User::ROLE_SUPER_ADMIN);

        return $user;
    }

    private function attendance($employee, string $date, string $in, string $out, array $extra = []): Attendance
    {
        $inAt = CarbonImmutable::parse("{$date} {$in}");
        $outAt = CarbonImmutable::parse("{$date} {$out}");
        if ($outAt->lt($inAt)) {
            $outAt = $outAt->addDay();
        }

        return Attendance::create($extra + [
            'employee_id' => $employee->id, 'project_id' => $this->project->id, 'work_date' => $date,
            'check_in_at' => $inAt, 'check_out_at' => $outAt, 'duration_minutes' => (int) $inAt->diffInMinutes($outAt),
            'check_in_lat' => self::LAT, 'check_in_lng' => self::LNG, 'check_out_lat' => self::LAT, 'check_out_lng' => self::LNG,
            'status' => 'hadir',
        ]);
    }

    public function test_individual_rows_follow_reference_format(): void
    {
        $employee = $this->makeEmployee($this->project);
        $this->attendance($employee, '2026-03-26', '08:03', '17:31');
        $this->attendance($employee, '2026-03-25', '08:00', '01:00'); // pulang lewat tengah malam

        $service = app(ReportService::class);
        $range = $service->range($this->project, ['period' => 1]);
        $assignment = $service->assignments($this->project, $range['from'], $range['to'])->first();
        $rows = $service->individualRows($this->project, $assignment, $range['from'], $range['to'], false);

        $this->assertCount(30, $rows); // 25/02 – 26/03
        $this->assertSame('26/03/2026', $rows[0]['date']); // terbaru di atas, seperti acuan
        $this->assertSame('08:03:00', $rows[0]['check_in']);
        $this->assertSame('9 jam 28 menit', $rows[0]['duration']);
        $this->assertSame('-7.3154, 112.6855', $rows[0]['coord_in']);
        $this->assertSame('01:00:00 (+1)', $rows[1]['check_out']);
        $this->assertSame('17 jam 0 menit', $rows[1]['duration']);
        $this->assertSame('-', $rows[2]['status']); // belum ada data
    }

    public function test_individual_pdf_is_generated_and_downloadable(): void
    {
        $employee = $this->makeEmployee($this->project);
        $this->attendance($employee, '2026-03-26', '08:03', '17:31');
        $this->actingAs($this->admin());

        $res = $this->postJson('/reports', [
            'type' => 'individual', 'format' => 'pdf', 'range_mode' => 'period', 'period' => 1,
            'employee_id' => $employee->id, 'photos' => '1',
        ])->assertOk()->assertJsonPath('success', true);

        $job = ReportJob::sole();
        $this->assertSame('done', $job->status);
        $this->assertStringContainsString('Bulan ke-1', $job->title);

        $pdf = $this->get($res->json('data.download_url'))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());
    }

    public function test_recap_excel_contains_matrix_and_detail(): void
    {
        $a = $this->makeEmployee($this->project);
        $b = $this->makeEmployee($this->project);
        $this->attendance($a, '2026-03-02', '08:00', '17:00');
        $this->attendance($b, '2026-03-02', '09:00', '17:00', ['status' => 'terlambat', 'check_in_mode' => 'offsite']);
        $this->actingAs($this->admin());

        $res = $this->postJson('/reports', [
            'type' => 'recap', 'format' => 'xlsx', 'range_mode' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-03',
        ])->assertOk();

        $file = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($file, $this->get($res->json('data.download_url'))->streamedContent());
        $book = IOFactory::load($file);
        $recap = $book->getSheetByName('Rekap');

        $this->assertSame('H', $recap->getCell('E5')->getValue());   // 2 Mar, karyawan A
        $this->assertSame('T*', $recap->getCell('E6')->getValue());  // terlambat + luar lokasi
        $this->assertSame('-', $recap->getCell('D5')->getValue());   // 1 Mar tanpa data
        // Detail: header + 2 karyawan × 3 hari
        $this->assertSame(7, $book->getSheetByName('Detail')->getHighestRow());
        @unlink($file);
    }

    public function test_large_combined_report_is_queued(): void
    {
        Queue::fake();
        config(['presensi.report_sync_max_rows' => 10]);
        $this->makeEmployee($this->project);
        $this->actingAs($this->admin());

        $this->postJson('/reports', ['type' => 'combined', 'format' => 'pdf', 'range_mode' => 'period', 'period' => 1, 'photos' => '0'])
            ->assertOk()
            ->assertJsonPath('data.queued', true);

        Queue::assertPushed(GenerateReport::class);
        $this->assertSame('queued', ReportJob::sole()->status);
    }

    public function test_individual_report_needs_employee(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/reports', ['type' => 'individual', 'format' => 'xlsx', 'range_mode' => 'period', 'period' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    public function test_individual_excel_follows_pdf_columns(): void
    {
        $employee = $this->makeEmployee($this->project);
        $this->attendance($employee, '2026-03-02', '08:00', '17:00', ['flags' => [Attendance::FLAG_BACKDATED]]);
        $this->actingAs($this->admin());

        $book = $this->downloadExcel([
            'type' => 'individual', 'format' => 'xlsx', 'range_mode' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-03',
            'employee_id' => $employee->id, 'photos' => '1',
        ]);

        $this->assertSame(1, $book->getSheetCount());
        $sheet = $book->getSheet(0);
        $this->assertSame('Laporan Absensi Harian', $sheet->getCell('A1')->getValue());
        $this->assertSame('Tanggal', $sheet->getCell('A5')->getValue());
        $this->assertSame('02/03/2026', $sheet->getCell('A7')->getValue());
        $this->assertSame('08:00:00', $sheet->getCell('E7')->getValue());
        $this->assertSame('Hadir', $sheet->getCell('I7')->getValue());
        $this->assertSame('Tanggal mundur', $sheet->getCell('J7')->getValue());
        $this->assertSame('9 jam 0 menit', $sheet->getCell('K7')->getValue());
        $this->assertNull(ReportJob::sole()->params['photos'] ?? null);
    }

    public function test_combined_excel_has_sheet_per_employee_and_all_rows_sheet(): void
    {
        // Nama sama → nama sheet dibuat unik
        $a = $this->makeEmployee($this->project);
        $b = $this->makeEmployee($this->project);
        $this->attendance($a, '2026-03-02', '08:00', '17:00');
        $this->attendance($b, '2026-03-02', '09:00', '17:00');
        $this->actingAs($this->admin());

        $book = $this->downloadExcel([
            'type' => 'combined', 'format' => 'xlsx', 'range_mode' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-03',
        ]);

        $this->assertSame(['Semua Karyawan', 'Karyawan Uji', 'Karyawan Uji (2)'], $book->getSheetNames());
        // Header + 2 karyawan × 3 hari
        $this->assertSame(7, $book->getSheetByName('Semua Karyawan')->getHighestRow());
    }

    private function downloadExcel(array $payload): Spreadsheet
    {
        $res = $this->postJson('/reports', $payload)->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($file, $this->get($res->json('data.download_url'))->streamedContent());
        $book = IOFactory::load($file);
        @unlink($file);

        return $book;
    }

    public function test_team_leader_can_create_reports_but_not_for_other_projects(): void
    {
        $leader = $this->makeEmployee($this->project, ['is_team_leader' => true]);
        $leader->user->assignRole(User::ROLE_TEAM_LEADER);
        $other = $this->makeProject();
        $foreign = ReportJob::create([
            'uuid' => (string) Str::uuid(), 'project_id' => $other->id, 'type' => 'recap',
            'format' => 'pdf', 'params' => ['period' => 1], 'title' => 'x', 'status' => 'done', 'file_path' => 'reports/x.pdf',
        ]);
        $this->actingAs($leader->user);

        $this->get('/reports')->assertOk();
        $this->postJson('/reports', ['type' => 'recap', 'format' => 'pdf', 'range_mode' => 'period', 'period' => 1])->assertOk();
        $this->get("/reports/{$foreign->id}/download")->assertNotFound();
    }

    public function test_signatures_render_multiple_columns(): void
    {
        $employee = $this->makeEmployee($this->project);
        $this->project->signatories()->createMany([
            ['label' => 'Dibuat', 'name' => 'Edi Santoso, S.T., M.T.', 'title' => 'Team Leader', 'sort_order' => 1],
            ['label' => 'Mengetahui', 'name' => 'Ir. Pejabat PPK', 'title' => 'PPK', 'sort_order' => 2],
        ]);

        $html = view('reports.pdf.individual', [
            'project' => $this->project, 'title' => 't', 'from' => CarbonImmutable::parse('2026-02-25'), 'to' => CarbonImmutable::parse('2026-03-26'),
            'label' => 'Bulan ke-1', 'printedAt' => 'x', 'withPhotos' => false,
            'footer' => app(ReportService::class)->footer($this->project, CarbonImmutable::parse('2026-03-26')),
            'sections' => collect([['employee' => $employee, 'rows' => []]]),
        ])->render();

        $this->assertStringContainsString('Mengetahui', $html);
        $this->assertStringContainsString('Surabaya, 26 Maret 2026', $html);
        $this->assertStringContainsString('width: 50%', $html);
    }
}
