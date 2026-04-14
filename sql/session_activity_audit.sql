-- Session & Activity Audit Center schema
-- Run on database: db

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `role_name` VARCHAR(120) NOT NULL DEFAULT '',
  `session_id_hash` CHAR(64) NOT NULL,
  `login_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `logout_at` DATETIME NULL DEFAULT NULL,
  `status` ENUM('active','expired','forced_logout') NOT NULL DEFAULT 'active',
  `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `device` VARCHAR(190) NOT NULL DEFAULT '',
  `location` VARCHAR(160) NOT NULL DEFAULT 'Unknown',
  `last_activity_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `invalidated_at` DATETIME NULL DEFAULT NULL,
  `invalidated_by_user_id` INT NULL DEFAULT NULL,
  `invalidated_reason` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_sessions_user` (`user_id`, `login_at`),
  KEY `idx_user_sessions_email` (`email`, `login_at`),
  KEY `idx_user_sessions_status` (`status`, `last_activity_at`),
  KEY `idx_user_sessions_role` (`role_name`, `login_at`),
  KEY `idx_user_sessions_hash` (`session_id_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL DEFAULT NULL,
  `email` VARCHAR(190) NULL DEFAULT NULL,
  `user_name` VARCHAR(190) NULL DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `module` VARCHAR(120) NOT NULL,
  `record_type` VARCHAR(120) NULL DEFAULT NULL,
  `record_id` VARCHAR(120) NULL DEFAULT NULL,
  `entity_type` VARCHAR(80) NULL DEFAULT NULL,
  `entity_id` BIGINT NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `snapshot_json` LONGTEXT NULL DEFAULT NULL,
  `before_data` LONGTEXT NULL DEFAULT NULL,
  `after_data` LONGTEXT NULL DEFAULT NULL,
  `ip_address` VARCHAR(64) NULL DEFAULT NULL,
  `location` VARCHAR(160) NULL DEFAULT NULL,
  `device` VARCHAR(190) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user_created` (`user_id`, `created_at`),
  KEY `idx_audit_email_created` (`email`, `created_at`),
  KEY `idx_audit_action_created` (`action`, `created_at`),
  KEY `idx_audit_module_created` (`module`, `created_at`),
  KEY `idx_audit_record_created` (`record_type`, `record_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `deleted_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_name` VARCHAR(120) NOT NULL,
  `source_table` VARCHAR(120) NOT NULL,
  `record_type` VARCHAR(120) NOT NULL,
  `record_id` VARCHAR(120) NOT NULL,
  `item_identifier` VARCHAR(255) NOT NULL,
  `deleted_by_user_id` INT NOT NULL,
  `deleted_by_email` VARCHAR(190) NOT NULL,
  `deleted_reason` VARCHAR(255) NULL DEFAULT NULL,
  `record_snapshot` LONGTEXT NOT NULL,
  `deleted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('deleted','restored','purged') NOT NULL DEFAULT 'deleted',
  `restored_at` DATETIME NULL DEFAULT NULL,
  `restored_by_user_id` INT NULL DEFAULT NULL,
  `permanently_deleted_at` DATETIME NULL DEFAULT NULL,
  `permanently_deleted_by_user_id` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deleted_items_deleted_at` (`deleted_at`),
  KEY `idx_deleted_items_module` (`module_name`, `deleted_at`),
  KEY `idx_deleted_items_status` (`status`, `deleted_at`),
  KEY `idx_deleted_items_email` (`deleted_by_email`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ip_location_cache` (
  `ip_address` VARCHAR(64) NOT NULL,
  `location_label` VARCHAR(160) NOT NULL DEFAULT 'Unknown',
  `cached_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`ip_address`),
  KEY `idx_ip_location_expire` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
