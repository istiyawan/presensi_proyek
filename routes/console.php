<?php

use Illuminate\Support\Facades\Schedule;

/*
| Cukup satu cron di server (VPS maupun shared hosting):
|   * * * * * php /path/ke/backend/artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('attendance:close-day')->dailyAt('00:30')->withoutOverlapping();

// Shared hosting tidak bisa menjalankan worker permanen (Supervisor), jadi
// queue diproses lewat scheduler tiap menit. Di VPS set QUEUE_VIA_SCHEDULER=false
// dan jalankan `php artisan queue:work` dengan Supervisor.
if (config('presensi.queue_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --timeout=900 --tries=1')
        ->everyMinute()
        ->withoutOverlapping();
}

// Lupa check-out ditandai setelah melewati batas jam kerja (pengaturan max_work_hours),
// bukan saat tutup hari — shift bisa berlanjut lewat tengah malam.
Schedule::command('attendance:flag-missing-checkout')->hourlyAt(5)->withoutOverlapping();

Schedule::command('reports:prune')->dailyAt('02:00');
