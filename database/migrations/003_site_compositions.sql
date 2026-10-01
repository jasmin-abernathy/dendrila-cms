CREATE TABLE site_compositions (
    site_id VARCHAR(64) NOT NULL,
    version BIGINT UNSIGNED NOT NULL DEFAULT 0,
    draft_json LONGTEXT NULL,
    published_json LONGTEXT NULL,
    published_version BIGINT UNSIGNED NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id),
    CONSTRAINT fk_composition_site FOREIGN KEY (site_id) REFERENCES sites(site_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE composition_revisions (
    site_id VARCHAR(64) NOT NULL,
    version BIGINT UNSIGNED NOT NULL,
    operation ENUM('draft','publish','restore') NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    actor_id VARCHAR(128) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, version),
    CONSTRAINT fk_composition_revision_site FOREIGN KEY (site_id) REFERENCES sites(site_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO role_permissions (site_id, role_id, permission)
SELECT site_id, role_id, 'composition.manage' FROM roles WHERE role_id IN ('owner', 'admin');

INSERT INTO role_permissions (site_id, role_id, permission)
SELECT site_id, role_id, 'composition.publish' FROM roles WHERE role_id IN ('owner', 'admin');
