CREATE TABLE IF NOT EXISTS native_mmw_pair_codes (
  site_id VARCHAR(64) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  actor_id VARCHAR(128) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  consumed_at DATETIME(6) NULL,
  PRIMARY KEY(site_id,code_hash),
  KEY ix_mmw_actor_created(site_id,actor_id,created_at),
  CONSTRAINT fk_mmw_pair_actor FOREIGN KEY(site_id,actor_id)
    REFERENCES native_accounts(site_id,actor_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS native_mmw_pair_limits (
  site_id VARCHAR(64) NOT NULL,
  bucket CHAR(64) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  resets_at BIGINT NOT NULL,
  PRIMARY KEY(site_id,bucket),
  CONSTRAINT fk_mmw_limits_site FOREIGN KEY(site_id)
    REFERENCES sites(site_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
