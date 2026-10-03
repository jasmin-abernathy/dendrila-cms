CREATE TABLE device_pairing_codes (
    code_hash CHAR(64) NOT NULL,
    site_id VARCHAR(64) NOT NULL,
    actor_id VARCHAR(128) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (code_hash),
    KEY ix_pairing_codes_expiry (expires_at),
    CONSTRAINT fk_pairing_codes_membership FOREIGN KEY (site_id, actor_id)
        REFERENCES memberships(site_id, actor_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
