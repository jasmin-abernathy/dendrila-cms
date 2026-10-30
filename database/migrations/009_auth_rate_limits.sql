CREATE TABLE IF NOT EXISTS auth_rate_limits (
    site_id VARCHAR(64) NOT NULL,
    scope VARCHAR(64) NOT NULL,
    window_started_at DATETIME(6) NOT NULL,
    failures SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, scope),
    CONSTRAINT fk_auth_rate_limits_site FOREIGN KEY (site_id)
        REFERENCES sites(site_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
