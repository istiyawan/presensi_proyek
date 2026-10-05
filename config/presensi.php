<?php

/*
|--------------------------------------------------------------------------
| Nilai default pengaturan
|--------------------------------------------------------------------------
| Nilai di sini dipakai bila belum ada override di tabel app_settings /
| project_settings. Ubah lewat web admin, bukan di file ini.
*/

return [

    'app' => [
        'company_name' => env('PRESENSI_COMPANY_NAME', 'Nama Perusahaan'),
        'app_name' => env('APP_NAME', 'Presensi Proyek'),
        'logo' => null,
        'primary_color' => '#1E5AA8',
        // 1 akun = 1 perangkat aktif
        'device_binding' => true,
        // versi minimum aplikasi mobile (paksa update)
        'min_app_version' => '1.0.0',
    ],

    'project' => [
        // Periode laporan: tgl mulai s/d tgl akhir di bulan berikutnya (acuan: 25 → 26)
        'period_start_day' => 25,
        'period_end_day' => 26,
        'period_end_next_month' => true,

        // ISO-8601: 1 = Senin ... 7 = Minggu
        'working_days' => [1, 2, 3, 4, 5, 6, 7],
        'use_national_holidays' => false,

        // Lokasi
        'allow_offsite' => false,
        'offsite_scope' => 'selected', // all | selected
        'offsite_requires_approval' => false,
        'offsite_requires_note' => true,
        'max_gps_accuracy_m' => 50,
        'block_mock_location' => true,
        'require_checkout_in_location' => true,

        // Jendela check-out sejak check-in (jam). Check-out lewat tengah malam
        // (mis. masuk 08:00, pulang 01:00 esoknya) tetap masuk ke tanggal kerja
        // check-in. Lewat batas ini presensi dianggap lupa check-out.
        'max_work_hours' => 20,

        // Karyawan boleh memilih tanggal presensi mundur sampai sekian hari
        // (null = bebas, 0 = hanya hari ini)
        'backdate_max_days' => null,

        // Offline
        'allow_offline' => true,
        'offline_max_hours' => 72,

        // Laporan
        'report_city' => null,
        'report_sort' => 'desc',
        'report_show_photo' => true,
    ],

    'photo' => [
        'disk' => 'local',
        'max_kb' => 5120,
        // Foto diperkecil saat disimpan: sisi terpanjang dibatasi + kompresi JPEG.
        'max_width' => (int) env('PHOTO_MAX_WIDTH', 640),
        'thumb_width' => 160,
        'quality' => (int) env('PHOTO_QUALITY', 60),
    ],

    // Toleransi selisih jam perangkat ke depan (detik) untuk data offline
    'future_tolerance_seconds' => 300,

    // Laporan "semua karyawan" di atas bobot ini (karyawan × hari, ×2 bila dengan foto) diproses
    // di antrean. 600 ≈ 10 karyawan × 30 hari berfoto (±25 detik). Turunkan bila batas waktu
    // eksekusi PHP di hosting 30 detik atau kurang.
    'report_sync_max_rows' => (int) env('REPORT_SYNC_MAX_ROWS', 600),

    // Berkas laporan lebih tua dari ini dihapus otomatis (hari)
    'report_retention_days' => 14,

    // true untuk shared hosting (queue diproses scheduler), false di VPS + Supervisor
    'queue_via_scheduler' => env('QUEUE_VIA_SCHEDULER', true),

    // White-label: 1 deployment = 1 proyek (klien baru = server baru). Struktur data tetap
    // mendukung banyak proyek; true membuka kembali "Proyek Baru" & pemilih proyek di topbar.
    'multi_project' => (bool) env('PRESENSI_MULTI_PROJECT', false),

];
