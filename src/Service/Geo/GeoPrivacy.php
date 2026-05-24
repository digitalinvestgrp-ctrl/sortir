<?php
declare(strict_types=1);

namespace App\Service\Geo;

/**
 * Garde-fou trust & safety (VETO Tomas — porte de sortir/app/Services/Geo/GeoPrivacy.php)
 *
 * La geoloc d'un MEMBRE est toujours stockee au quartier, JAMAIS au domicile exact.
 * Pour les sorties (events) et etablissements pro (vitrines), on peut etre plus precis.
 *
 * Snap-to-grid : 0.01 degre = ~1.1 km en latitude. Empêche tout doxing.
 */
class GeoPrivacy
{
    public static function coarsen(float $lat, float $lng, int $decimals = 2): array
    {
        return [
            'lat' => round($lat, $decimals),
            'lng' => round($lng, $decimals),
        ];
    }

    /**
     * Distance Haversine en metres (controle/test, la vraie recherche utilise ST_Distance_Sphere)
     */
    public static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6_371_000.0; // metres
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
