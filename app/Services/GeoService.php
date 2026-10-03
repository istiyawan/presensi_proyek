<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectLocation;

class GeoService
{
    private const EARTH_RADIUS_M = 6371000;

    public function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Titik lokasi proyek terdekat.
     *
     * @return array{location: ?ProjectLocation, distance: ?float, inside: bool}
     */
    public function nearest(Project $project, float $lat, float $lng): array
    {
        $best = ['location' => null, 'distance' => null, 'inside' => false];

        foreach ($project->locations()->where('is_active', true)->get() as $location) {
            $d = $this->distance($lat, $lng, $location->latitude, $location->longitude);
            if ($best['distance'] === null || $d < $best['distance']) {
                $best = ['location' => $location, 'distance' => $d, 'inside' => $d <= $location->radius_m];
            }
        }

        return $best;
    }
}
