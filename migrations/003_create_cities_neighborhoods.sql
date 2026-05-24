-- Migration 003 : cities + neighborhoods (avec colonne SPATIAL POINT)
-- Centroid des quartiers utilise pour : filtres carte, rattachement membres,
-- granularite trust (on n'expose JAMAIS le GPS exact d'un membre — veto Tomas)

CREATE TABLE IF NOT EXISTS cities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  country_code CHAR(2) NOT NULL DEFAULT 'FR',
  is_pilot TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS neighborhoods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  city_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  -- POINT SRID 4326 (WGS84). Avec MySQL 8.0.12+, axis order = lat/lng quand on
  -- utilise ST_GeomFromText avec SRID 4326. Pour ce projet on standardise sur
  -- POINT(lng, lat) sans contrainte SRID et on utilise ST_Distance_Sphere
  -- qui ne s'occupe pas de l'axis order (formule Haversine pure).
  centroid POINT NOT NULL SRID 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_city_neighborhood_slug (city_id, slug),
  SPATIAL INDEX idx_neighborhoods_centroid (centroid),
  CONSTRAINT fk_neighborhoods_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
