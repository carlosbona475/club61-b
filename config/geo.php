<?php
/**
 * Distância em linha reta na superfície da Terra (Haversine).
 */

declare(strict_types=1);

/**
 * @return float Distância em quilómetros (não negativa)
 */
function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earthKm = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $a = min(1.0, max(0.0, $a));
    $c = 2 * atan2(sqrt($a), sqrt(1.0 - $a));

    return $earthKm * $c;
}
