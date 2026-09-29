CREATE TABLE devices (
    site_id VARCHAR(64) NOT NULL,
    device_id VARCHAR(64) NOT NULL,
    actor_id VARCHAR(128) NOT NULL,
    device_name VARCHAR(120) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    last_seen_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    PRIMARY KEY (site_id, device_id),
    UNIQUE KEY uq_devices_token_hash (token_hash),
    KEY ix_devices_actor (site_id, actor_id, revoked_at),
    CONSTRAINT fk_devices_membership FOREIGN KEY (site_id, actor_id)
        REFERENCES memberships(site_id, actor_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_api_settings (
    site_id VARCHAR(64) NOT NULL,
    branding_json LONGTEXT NOT NULL,
    modules_json LONGTEXT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id),
    CONSTRAINT fk_site_api_settings_site FOREIGN KEY (site_id)
        REFERENCES sites(site_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_api_settings (site_id, branding_json, modules_json, created_at, updated_at)
SELECT site_id, '{}', '[]', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM sites;
