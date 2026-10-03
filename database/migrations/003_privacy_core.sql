CREATE TABLE privacy_settings (
    site_id VARCHAR(64) NOT NULL,
    settings_json LONGTEXT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id),
    CONSTRAINT fk_privacy_settings_site FOREIGN KEY (site_id)
        REFERENCES sites(site_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE privacy_scans (
    site_id VARCHAR(64) NOT NULL,
    scan_id VARCHAR(64) NOT NULL,
    mode ENUM('standard','full') NOT NULL,
    status ENUM('completed','cancelled','failed') NOT NULL,
    started_by_id VARCHAR(128) NULL,
    source ENUM('web','mmw','import','system') NOT NULL,
    summary_json LONGTEXT NOT NULL,
    started_at DATETIME(6) NOT NULL,
    finished_at DATETIME(6) NULL,
    PRIMARY KEY (site_id, scan_id),
    KEY ix_privacy_scans_site_finished (site_id, status, finished_at),
    CONSTRAINT fk_privacy_scans_site FOREIGN KEY (site_id)
        REFERENCES sites(site_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE privacy_findings (
    site_id VARCHAR(64) NOT NULL,
    scan_id VARCHAR(64) NOT NULL,
    finding_id VARCHAR(64) NOT NULL,
    kind ENUM('tracker','third_party_service','legal_gap','consent_issue','external_practice') NOT NULL,
    service_id VARCHAR(64) NULL,
    label VARCHAR(190) NOT NULL,
    category ENUM('statistics','marketing','external_media','functional','unknown') NOT NULL,
    status ENUM('detected','needs_review','documented','resolved') NOT NULL,
    optional_tracking BOOLEAN NOT NULL DEFAULT FALSE,
    evidence_json LONGTEXT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, scan_id, finding_id),
    KEY ix_privacy_findings_service (site_id, service_id, status),
    CONSTRAINT fk_privacy_findings_scan FOREIGN KEY (site_id, scan_id)
        REFERENCES privacy_scans(site_id, scan_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO privacy_settings (site_id, settings_json, created_at, updated_at)
SELECT site_id,
       '{"consent":{"enabled":false,"layout":"bar","retention_days":180,"footer_link":true},"scan":{"schedule":"off"},"legal_profile":{},"external_practices":[]}',
       UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM sites;

INSERT IGNORE INTO role_permissions (site_id, role_id, permission)
SELECT site_id, role_id, 'privacy.read'
FROM roles
WHERE role_id IN ('owner', 'admin', 'editor');

INSERT IGNORE INTO role_permissions (site_id, role_id, permission)
SELECT site_id, role_id, 'privacy.manage'
FROM roles
WHERE role_id IN ('owner', 'admin');

INSERT IGNORE INTO role_permissions (site_id, role_id, permission)
SELECT site_id, role_id, 'privacy.scan'
FROM roles
WHERE role_id IN ('owner', 'admin', 'editor');
