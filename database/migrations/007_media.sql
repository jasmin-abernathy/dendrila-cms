CREATE TABLE IF NOT EXISTS media_assets (
    site_id VARCHAR(64) NOT NULL,
    media_id VARCHAR(64) NOT NULL,
    original_key VARCHAR(190) NOT NULL,
    mime VARCHAR(64) NOT NULL,
    bytes BIGINT UNSIGNED NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    metadata_json LONGTEXT NOT NULL,
    author_id VARCHAR(128) NOT NULL,
    state ENUM('ready','deleting','deleted') NOT NULL DEFAULT 'ready',
    rotation SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, media_id),
    CONSTRAINT fk_media_site FOREIGN KEY (site_id) REFERENCES sites(site_id),
    KEY ix_media_list (site_id, state, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_variants (
    site_id VARCHAR(64) NOT NULL,
    media_id VARCHAR(64) NOT NULL,
    version BIGINT UNSIGNED NOT NULL,
    kind ENUM('thumb','web') NOT NULL,
    storage_key VARCHAR(190) NOT NULL,
    public_token CHAR(48) NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    bytes BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (site_id, media_id, version, kind),
    UNIQUE KEY uq_media_public_token (public_token),
    CONSTRAINT fk_media_variant_asset FOREIGN KEY (site_id, media_id)
        REFERENCES media_assets(site_id, media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
