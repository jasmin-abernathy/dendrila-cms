CREATE TABLE IF NOT EXISTS native_accounts (
 site_id VARCHAR(64) NOT NULL,
 actor_id VARCHAR(128) NOT NULL,
 email VARCHAR(254) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 totp_cipher TEXT NOT NULL,
 last_totp_step BIGINT NOT NULL DEFAULT -1,
 enabled BOOLEAN NOT NULL DEFAULT TRUE,
 session_version INT NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (site_id, actor_id),
 UNIQUE KEY uq_native_email (site_id, email),
 CONSTRAINT fk_native_membership FOREIGN KEY (site_id, actor_id) REFERENCES memberships(site_id, actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS native_login_limits (
 site_id VARCHAR(64) NOT NULL,
 bucket CHAR(64) NOT NULL,
 attempts INT NOT NULL,
 resets_at BIGINT NOT NULL,
 PRIMARY KEY (site_id,bucket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
