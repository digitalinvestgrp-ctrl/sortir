<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Pdo;

class NeighborhoodRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    public function findBySlugAndCity(string $slug, int $cityId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, city_id, name, slug, ST_X(centroid) AS lng, ST_Y(centroid) AS lat
             FROM neighborhoods WHERE city_id = ? AND slug = ? LIMIT 1'
        );
        $stmt->execute([$cityId, $slug]);
        return $stmt->fetch() ?: null;
    }

    public function listByCity(int $cityId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, ST_X(centroid) AS lng, ST_Y(centroid) AS lat
             FROM neighborhoods WHERE city_id = ? ORDER BY name'
        );
        $stmt->execute([$cityId]);
        return $stmt->fetchAll();
    }
}
