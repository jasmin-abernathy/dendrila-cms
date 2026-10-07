CREATE TABLE navigation_menus (
    site_id VARCHAR(64) NOT NULL,
    menu_id VARCHAR(64) NOT NULL,
    label VARCHAR(190) NOT NULL,
    location VARCHAR(64) NOT NULL,
    items_json LONGTEXT NOT NULL,
    version BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (site_id, menu_id),
    UNIQUE KEY uq_navigation_location (site_id, location),
    CONSTRAINT fk_navigation_site FOREIGN KEY (site_id) REFERENCES sites(site_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
