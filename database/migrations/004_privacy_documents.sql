CREATE TABLE privacy_documents (
    site_id VARCHAR(64) NOT NULL,
    kind ENUM('legal_notice','privacy','cookies') NOT NULL,
    html LONGTEXT NOT NULL,
    source_hash CHAR(64) NOT NULL,
    approved_by_id VARCHAR(128) NOT NULL,
    approved_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, kind),
    CONSTRAINT fk_privacy_documents_site FOREIGN KEY (site_id)
        REFERENCES sites(site_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
