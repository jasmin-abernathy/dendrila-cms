CREATE TABLE seo_metadata (
    site_id VARCHAR(64) NOT NULL,
    content_id VARCHAR(64) NOT NULL,
    title VARCHAR(190) NULL,
    description VARCHAR(500) NULL,
    canonical_url VARCHAR(2048) NULL,
    social_title VARCHAR(190) NULL,
    social_description VARCHAR(500) NULL,
    social_image_url VARCHAR(2048) NULL,
    indexable BOOLEAN NOT NULL DEFAULT TRUE,
    version BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, content_id),
    CONSTRAINT fk_seo_content FOREIGN KEY (site_id, content_id)
        REFERENCES contents(site_id, content_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
