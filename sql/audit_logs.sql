-- Audit Logs table (MySQL/MariaDB)
-- Create once in your database (e.g. `db`).

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  `user_id` INT NULL,
  `user_name` VARCHAR(150) NULL,

  `action` VARCHAR(50) NOT NULL,
  `module` VARCHAR(120) NOT NULL,

  `entity_type` VARCHAR(80) NULL,
  `entity_id` BIGINT NULL,

  `description` VARCHAR(255) NULL,

  `before_data` JSON NULL,
  `after_data` JSON NULL,

  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,

  PRIMARY KEY (`id`),
  KEY `idx_audit_user_created` (`user_id`, `created_at`),
  KEY `idx_audit_module_created` (`module`, `created_at`),
  KEY `idx_audit_entity_created` (`entity_type`, `entity_id`, `created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

