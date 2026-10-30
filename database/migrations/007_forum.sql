CREATE TABLE forum_topics (
 site_id VARCHAR(64) NOT NULL, topic_id CHAR(32) NOT NULL, author_id VARCHAR(128) NOT NULL,
 title VARCHAR(160) NOT NULL, status ENUM('pending','published','hidden') NOT NULL DEFAULT 'pending',
 locked TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(site_id,topic_id), KEY ix_forum_topics_list(site_id,status,updated_at),
 KEY ix_forum_topics_author(site_id,author_id,created_at),
 FOREIGN KEY(site_id) REFERENCES sites(site_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE forum_messages (
 site_id VARCHAR(64) NOT NULL, message_id CHAR(32) NOT NULL, topic_id CHAR(32) NOT NULL,
 author_id VARCHAR(128) NOT NULL, body TEXT NOT NULL, is_opener TINYINT(1) NOT NULL DEFAULT 0,
 status ENUM('pending','published','hidden') NOT NULL DEFAULT 'pending',
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(site_id,message_id), KEY ix_forum_messages_topic(site_id,topic_id,status,created_at),
 KEY ix_forum_messages_author(site_id,author_id,created_at),
 FOREIGN KEY(site_id,topic_id) REFERENCES forum_topics(site_id,topic_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE forum_photos (
 site_id VARCHAR(64) NOT NULL, photo_id CHAR(32) NOT NULL, message_id CHAR(32) NOT NULL,
 alt_text VARCHAR(240) NOT NULL, width SMALLINT UNSIGNED NOT NULL, height SMALLINT UNSIGNED NOT NULL,
 byte_size INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(site_id,photo_id), KEY ix_forum_photos_message(site_id,message_id),
 FOREIGN KEY(site_id,message_id) REFERENCES forum_messages(site_id,message_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO role_permissions(site_id,role_id,permission)
 SELECT site_id,role_id,'forum.post' FROM roles WHERE role_id IN ('owner','admin','editor','contributor');
INSERT IGNORE INTO role_permissions(site_id,role_id,permission)
 SELECT site_id,role_id,'forum.moderate' FROM roles WHERE role_id IN ('owner','admin');
