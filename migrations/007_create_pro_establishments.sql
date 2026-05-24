-- Migration 007 : pro_establishments (bars, restos, salles) avec SPATIAL POINT

CREATE TABLE IF NOT EXISTS pro_establishments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  city_id BIGINT UNSIGNED NOT NULL,
  neighborhood_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'bar',
  description TEXT NULL,
  address_public VARCHAR(255) NULL,
  location POINT NOT NULL SRID 0,
  website VARCHAR(255) NULL,
  phone VARCHAR(32) NULL,
  plan VARCHAR(20) NOT NULL DEFAULT 'free',  -- free / pro_start / pro_plus
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pro_owner (owner_user_id),
  INDEX idx_pro_city (city_id),
  SPATIAL INDEX idx_pro_location (location),
  CONSTRAINT fk_pro_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pro_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE,
  CONSTRAINT fk_pro_neighborhood FOREIGN KEY (neighborhood_id) REFERENCES neighborhoods(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
