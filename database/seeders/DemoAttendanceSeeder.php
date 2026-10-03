<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectEmployee;
use App\Models\ProjectLocation;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\GeoService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

/**
 * Presensi demo lengkap (check-in, check-out, koordinat, foto) untuk pengembangan:
 * 1. 270 baris dari "ABSENSI BULAN 1.pdf" (25/02 – 26/03/2026);
 * 2. data buatan sejak hari berikutnya s/d kemarin — hari kerja hadir, hari libur nasional "libur".
 *
 * Foto berupa gambar placeholder bertanda nama/waktu. UUID & path foto deterministik,
 * jadi seeder aman dijalankan ulang (menimpa, tidak menumpuk file).
 */
class DemoAttendanceSeeder extends Seeder
{
    private Project $project;

    private ProjectLocation $location;

    public function __construct(private CalendarService $calendar, private GeoService $geo) {}

    public function run(): void
    {
        mt_srand(20260225);

        $this->project = Project::where('code', 'KDR')->firstOrFail();
        $this->location = $this->project->locations()->firstOrFail();
        $shift = $this->project->defaultShift();
        $tz = $this->project->timezone;

        // 1. Data impor dari PDF acuan
        $rows = json_decode(file_get_contents(database_path('data/absensi_bulan_1.json')), true);
        $employees = User::with('employee')->whereIn('username', array_unique(array_column($rows, 'username')))
            ->get()->mapWithKeys(fn ($u) => [$u->username => $u->employee]);

        foreach ($rows as $row) {
            $this->present(
                $employees[$row['username']],
                $row['date'],
                CarbonImmutable::parse("{$row['date']} {$row['check_in']}", $tz),
                CarbonImmutable::parse("{$row['date']} {$row['check_out']}", $tz),
                ['status' => $row['status'], 'shift_id' => $shift->id, 'is_manual' => true, 'note' => 'Impor dari ABSENSI BULAN 1.pdf'],
            );
        }

        // 2. Data buatan s/d kemarin (hari ini dibiarkan kosong untuk uji presensi dari aplikasi)
        $from = CarbonImmutable::parse(max(array_column($rows, 'date')), $tz)->addDay();
        $to = CarbonImmutable::yesterday($tz);
        $assignments = ProjectEmployee::with('employee')->where('project_id', $this->project->id)->get();

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $day = CarbonImmutable::parse($day->toDateString(), $tz);
            $date = $day->toDateString();
            $working = $this->calendar->isWorkingDay($this->project, $day);

            foreach ($assignments as $assignment) {
                if ($assignment->start_date->toDateString() > $date
                    || ($assignment->end_date && $assignment->end_date->toDateString() < $date)) {
                    continue;
                }
                $shiftId = $assignment->shift_id ?? $shift->id;

                if (! $working) {
                    Attendance::updateOrCreate(
                        ['employee_id' => $assignment->employee_id, 'project_id' => $this->project->id, 'work_date' => $date],
                        ['status' => Attendance::STATUS_LIBUR, 'shift_id' => $shiftId],
                    );

                    continue;
                }

                $in = $day->setTime(7, 10)->addMinutes(mt_rand(0, 55))->addSeconds(mt_rand(0, 59));
                $out = $day->setTime(17, 0)->addMinutes(mt_rand(0, 60))->addSeconds(mt_rand(0, 59));
                $this->present($assignment->employee, $date, $in, $out, ['status' => Attendance::STATUS_HADIR, 'shift_id' => $shiftId]);
            }
        }
    }

    private function present(Employee $employee, string $date, CarbonImmutable $in, CarbonImmutable $out, array $attributes): void
    {
        $attributes['duration_minutes'] = (int) $in->diffInMinutes($out);

        foreach (['check_in' => $in, 'check_out' => $out] as $p => $time) {
            $suffix = $p === 'check_in' ? 'in' : 'out';
            $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, "presensi-demo:{$employee->id}:{$date}:{$suffix}")->toString();
            // Sebaran ±30 m di sekitar titik lokasi proyek, seperti GPS sungguhan
            $lat = round($this->location->latitude + mt_rand(-270, 270) / 1e6, 7);
            $lng = round($this->location->longitude + mt_rand(-270, 270) / 1e6, 7);
            $photo = $this->photo($employee, $time, $uuid, $suffix);

            $attributes += [
                "{$p}_uuid" => $uuid,
                "{$p}_at" => $time->setTimezone(config('app.timezone')),
                "{$p}_lat" => $lat,
                "{$p}_lng" => $lng,
                "{$p}_accuracy" => mt_rand(40, 150) / 10,
                "{$p}_location_id" => $this->location->id,
                "{$p}_distance_m" => (int) round($this->geo->distance($this->location->latitude, $this->location->longitude, $lat, $lng)),
                "{$p}_mode" => Attendance::MODE_ONSITE,
                "{$p}_photo" => $photo['photo'],
                "{$p}_thumb" => $photo['thumb'],
                "{$p}_received_at" => $time->addSeconds(mt_rand(1, 5))->setTimezone(config('app.timezone')),
            ];
        }

        Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'project_id' => $this->project->id, 'work_date' => $date],
            $attributes,
        );
    }

    /**
     * Foto placeholder "selfie": siluet + nama + cap waktu, disimpan seperti PhotoService.
     *
     * @return array{photo: string, thumb: string}
     */
    private function photo(Employee $employee, CarbonImmutable $time, string $uuid, string $suffix): array
    {
        $cfg = config('presensi.photo');
        $dir = sprintf('attendances/%d/%s', $this->project->id, $time->format('Y/m'));
        [$w, $h] = [480, 640];

        $img = imagecreatetruecolor($w, $h);
        $hue = crc32($employee->full_name);
        $bg = imagecolorallocate($img, 90 + $hue % 120, 110 + ($hue >> 8) % 100, 130 + ($hue >> 16) % 100);
        $skin = imagecolorallocate($img, 224, 186, 150);
        $shirt = imagecolorallocate($img, 40 + ($hue >> 4) % 60, 60, 90 + ($hue >> 12) % 100);
        $band = imagecolorallocatealpha($img, 0, 0, 0, 50);
        $white = imagecolorallocate($img, 255, 255, 255);

        imagefill($img, 0, 0, $bg);
        imagefilledellipse($img, $w / 2, 600, 400, 360, $shirt);
        imagefilledellipse($img, $w / 2, 270, 200, 250, $skin);
        imagefilledrectangle($img, 0, $h - 110, $w, $h, $band);

        $lines = [
            [5, ($suffix === 'in' ? 'CHECK-IN' : 'CHECK-OUT').'  '.$time->format('d/m/Y H:i:s')],
            [4, $employee->full_name],
            [3, $this->location->name.' - DEMO'],
        ];
        foreach ($lines as $i => [$font, $text]) {
            imagestring($img, $font, 16, $h - 98 + $i * 30, $text, $white);
        }

        $thumb = imagescale($img, $cfg['thumb_width']);
        $paths = ['photo' => "{$dir}/{$uuid}_{$suffix}.jpg", 'thumb' => "{$dir}/{$uuid}_{$suffix}_thumb.jpg"];
        Storage::disk($cfg['disk'])->put($paths['photo'], $this->jpeg($img, $cfg['quality']));
        Storage::disk($cfg['disk'])->put($paths['thumb'], $this->jpeg($thumb, 70));

        return $paths;
    }

    private function jpeg(GdImage $img, int $quality): string
    {
        ob_start();
        imagejpeg($img, null, $quality);

        return ob_get_clean();
    }
}
