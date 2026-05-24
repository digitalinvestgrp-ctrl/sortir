-- Migration 008 : events + rsvps (avec SPATIAL POINT sur events.location)

CREATE TABLE IF NOT EXISTS events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  host_user_id BIGINT UNSIGNED NOT NULL,
  establishment_id BIGINT UNSIGNED NULL,
  interest_group_id BIGINT UNSIGNED NULL,
  city_id BIGINT UNSIGNED NOT NULL,
  neighborhood_id BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category VARCHAR(60) NULL,
  starts_at DATETIME NOT NULL,
  capacity INT NULL,
  is_sponsored TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'published',  -- draft / published / cancelled / archived
  location POINT NOT NULL SRID 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_events_city_starts (city_id, starts_at),
  INDEX idx_events_host (host_user_id),
  INDEX idx_events_status_starts (status, starts_at),
  SPATIAL INDEX idx_events_location (location),
  CONSTRAINT fk_events_host FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_events_establishment FOREIGN KEY (establishment_id) REFERENCES pro_establishments(id) ON DELETE SET NULL,
  CONSTRAINT fk_events_group FOREIGN KEY (interest_group_id) REFERENCES interest_groups(id) ON DELETE SET NULL,
  CONSTRAINT fk_events_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE,
  CONSTRAINT fk_events_neighborhood FOREIGN KEY (neighborhood_id) REFERENCES neighborhoods(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rsvps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'going',  -- going / interested / cancelled
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_rsvp_event_user (event_id, user_id),
  CONSTRAINT fk_rsvps_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_rsvps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
