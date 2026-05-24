<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Pdo;

/**
 * EventRepository — requete nearby = portage critique PostGIS -> MySQL spatial
 *
 * Optimisation : MySQL SPATIAL INDEX accelere MBRContains/ST_Intersects mais PAS
 * ST_Distance_Sphere. On pre-filtre via bounding box (calcul lat/lng delta en PHP)
 * pour benefier de l'index SPATIAL, puis on affine avec ST_Distance_Sphere.
 */
class EventRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Recherche d'evenements dans un rayon (metres) autour d'un point.
     * Convention projet : POINT(lng, lat) SRID 0.
     */
    public function findNearby(float $lat, float $lng, int $radiusMeters, ?string $category = null): array
    {
        // Bounding box : 1 degre lat ~ 111 km, 1 degre lng ~ 111 * cos(lat) km
        $latDelta = $radiusMeters / 111_000.0;
        $lngDelta = $radiusMeters / (111_000.0 * max(0.01, cos(deg2rad($lat))));

        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;

        // BBox WKT : polygone simple (lng, lat order)
        $bboxWkt = sprintf(
            'POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
            $minLng, $minLat,
            $maxLng, $minLat,
            $maxLng, $maxLat,
            $minLng, $maxLat,
            $minLng, $minLat
        );

        $originWkt = sprintf('POINT(%F %F)', $lng, $lat);

        $hasCategory = !empty($category) ? 1 : 0;

        $sql = "
            SELECT e.id, e.title, e.description, e.category, e.starts_at,
                   e.capacity, e.is_sponsored,
                   c.name AS city, n.name AS neighborhood,
                   ST_Y(e.location) AS lat,
                   ST_X(e.location) AS lng,
                   ROUND(ST_Distance_Sphere(e.location, ST_GeomFromText(:origin, 0))) AS distance_m
            FROM events e
            JOIN cities c ON c.id = e.city_id
            LEFT JOIN neighborhoods n ON n.id = e.neighborhood_id
            WHERE MBRContains(ST_GeomFromText(:bbox, 0), e.location)
              AND ST_Distance_Sphere(e.location, ST_GeomFromText(:origin2, 0)) <= :radius
              AND e.status = 'published'
              AND e.starts_at >= NOW()
              AND (:has_category = 0 OR e.category = :category)
            ORDER BY e.is_sponsored DESC, distance_m ASC
            LIMIT 100
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':origin' => $originWkt,
            ':origin2' => $originWkt,
            ':bbox' => $bboxWkt,
            ':radius' => $radiusMeters,
            ':has_category' => $hasCategory,
            ':category' => $category ?? '',
        ]);
        return $stmt->fetchAll();
    }

    public function findByNeighborhood(int $neighborhoodId): array
    {
        $sql = "
            SELECT e.id, e.title, e.description, e.category, e.starts_at,
                   e.capacity, e.is_sponsored,
                   n.name AS neighborhood,
                   ST_Y(e.location) AS lat,
                   ST_X(e.location) AS lng
            FROM events e
            LEFT JOIN neighborhoods n ON n.id = e.neighborhood_id
            WHERE e.neighborhood_id = ?
              AND e.status = 'published'
              AND e.starts_at >= NOW()
            ORDER BY e.is_sponsored DESC, e.starts_at ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$neighborhoodId]);
        return $stmt->fetchAll();
    }
}
