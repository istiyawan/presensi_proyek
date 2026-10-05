<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Simpan foto presensi (storage privat) + thumbnail untuk laporan.
 * Memakai driver GD agar jalan di shared hosting.
 */
class PhotoService
{
    /**
     * @return array{photo: string, thumb: string}
     */
    public function storeAttendancePhoto(UploadedFile $file, int $projectId, string $uuid, string $suffix): array
    {
        $cfg = config('presensi.photo');
        $dir = sprintf('attendances/%d/%s', $projectId, now()->format('Y/m'));
        $manager = new ImageManager(new Driver);

        // Batasi sisi terpanjang (selfie umumnya potret) agar ukuran berkas kecil.
        $image = $manager->decodePath($file->getRealPath())->orient();
        $image->scaleDown(width: $cfg['max_width'], height: $cfg['max_width']);
        $photoPath = "{$dir}/{$uuid}_{$suffix}.jpg";
        Storage::disk($cfg['disk'])->put($photoPath, (string) $image->encodeUsingFileExtension('jpg', quality: $cfg['quality']));

        $thumb = $image->scale(width: $cfg['thumb_width']);
        $thumbPath = "{$dir}/{$uuid}_{$suffix}_thumb.jpg";
        Storage::disk($cfg['disk'])->put($thumbPath, (string) $thumb->encodeUsingFileExtension('jpg', quality: 70));

        return ['photo' => $photoPath, 'thumb' => $thumbPath];
    }
}
