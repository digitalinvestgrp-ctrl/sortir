-- Migration 001 : users + password_reset_tokens
-- Remplace : 2026_05_21_000001_create_users_table.php (Laravel)
-- Note : la table sessions Laravel n'est PAS portee (V1 = API token only, pas de session HTTP)

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  email_verified_at DATETIME NULL,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(32) NULL,
  birthdate DATE NOT NULL,
  phone_verified_at DATETIME NULL,
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,
  remember_token VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_email (email),
  INDEX idx_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  email VARCHAR(190) PRIMARY KEY,
  token VARCHAR(255) NOT NULL,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
