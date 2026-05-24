<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Pdo;

class ProEstablishmentRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, owner_user_id, city_id, neighborhood_id, name, category, description,
                    address_public, website, phone, plan, is_verified,
                    ST_X(location) AS lng, ST_Y(location) AS lat
             FROM pro_establishments WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function listByCity(int $cityId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, category, plan, is_verified, ST_X(location) AS lng, ST_Y(location) AS lat
             FROM pro_establishments WHERE city_id = ? ORDER BY is_verified DESC, name"
        );
        $stmt->execute([$cityId]);
        return $stmt->fetchAll();
    }
}
