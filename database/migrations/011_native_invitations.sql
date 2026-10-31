CREATE TABLE IF NOT EXISTS native_invitations (
 site_id VARCHAR(64) NOT NULL,
 token_hash CHAR(64) NOT NULL,
 email VARCHAR(254) NOT NULL,
 role_id VARCHAR(64) NOT NULL,
 expires_at BIGINT NOT NULL,
 created_by VARCHAR(128) NOT NULL,
 PRIMARY KEY(site_id,token_hash),
 CONSTRAINT fk_invitation_role FOREIGN KEY(site_id,role_id) REFERENCES roles(site_id,role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
