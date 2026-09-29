CREATE TABLE sites (
    site_id VARCHAR(64) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    config_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    data_revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    policy_json LONGTEXT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    site_id VARCHAR(64) NOT NULL,
    role_id VARCHAR(64) NOT NULL,
    label VARCHAR(190) NOT NULL,
    PRIMARY KEY (site_id, role_id),
    CONSTRAINT fk_roles_site FOREIGN KEY (site_id) REFERENCES sites(site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    site_id VARCHAR(64) NOT NULL,
    role_id VARCHAR(64) NOT NULL,
    permission VARCHAR(128) NOT NULL,
    PRIMARY KEY (site_id, role_id, permission),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (site_id, role_id)
        REFERENCES roles(site_id, role_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE memberships (
    site_id VARCHAR(64) NOT NULL,
    actor_id VARCHAR(128) NOT NULL,
    role_id VARCHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, actor_id),
    CONSTRAINT fk_memberships_role FOREIGN KEY (site_id, role_id)
        REFERENCES roles(site_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_types (
    site_id VARCHAR(64) NOT NULL,
    type_id VARCHAR(64) NOT NULL,
    definition_json LONGTEXT NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, type_id),
    CONSTRAINT fk_content_types_site FOREIGN KEY (site_id) REFERENCES sites(site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contents (
    site_id VARCHAR(64) NOT NULL,
    content_id VARCHAR(64) NOT NULL,
    created_by_id VARCHAR(128) NOT NULL,
    type_id VARCHAR(64) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    status ENUM('draft','review','scheduled','published','archived') NOT NULL,
    values_json LONGTEXT NOT NULL,
    published_values_json LONGTEXT NULL,
    published_slug VARCHAR(190) NULL,
    published_version BIGINT UNSIGNED NULL,
    published_revision_id VARCHAR(64) NULL,
    version BIGINT UNSIGNED NOT NULL,
    current_revision_id VARCHAR(64) NOT NULL,
    publish_at DATETIME(6) NULL,
    review_approved_at DATETIME(6) NULL,
    published_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, content_id),
    UNIQUE KEY uq_contents_slug (site_id, type_id, slug),
    UNIQUE KEY uq_published_slug (site_id, type_id, published_slug),
    KEY ix_contents_listing (site_id, type_id, status, updated_at),
    KEY ix_contents_due (status, publish_at),
    CONSTRAINT fk_contents_type FOREIGN KEY (site_id, type_id)
        REFERENCES content_types(site_id, type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE revisions (
    site_id VARCHAR(64) NOT NULL,
    revision_id VARCHAR(64) NOT NULL,
    content_id VARCHAR(64) NOT NULL,
    version BIGINT UNSIGNED NOT NULL,
    author_id VARCHAR(128) NULL,
    source ENUM('web','mmw','import','system') NOT NULL,
    change_summary VARCHAR(500) NULL,
    snapshot_json LONGTEXT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, revision_id),
    UNIQUE KEY uq_revision_version (site_id, content_id, version),
    CONSTRAINT fk_revisions_content FOREIGN KEY (site_id, content_id)
        REFERENCES contents(site_id, content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE idempotency_requests (
    site_id VARCHAR(64) NOT NULL,
    actor_id VARCHAR(128) NOT NULL,
    client_request_id VARCHAR(128) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    response_json LONGTEXT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, actor_id, client_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_events (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id VARCHAR(64) NOT NULL,
    actor_id VARCHAR(128) NULL,
    device_id VARCHAR(128) NULL,
    action VARCHAR(128) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id VARCHAR(64) NULL,
    outcome ENUM('allowed','denied','failed') NOT NULL,
    details_json LONGTEXT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (event_id),
    KEY ix_audit_site_time (site_id, created_at),
    KEY ix_audit_actor_time (site_id, actor_id, created_at),
    CONSTRAINT fk_audit_site FOREIGN KEY (site_id) REFERENCES sites(site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
