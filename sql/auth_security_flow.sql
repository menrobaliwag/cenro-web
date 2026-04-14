-- Authentication hardening schema
-- Use with existing city_enro database

CREATE TABLE IF NOT EXISTS auth_email_otp (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  purpose VARCHAR(40) NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts_used TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
  consumed_at DATETIME NULL DEFAULT NULL,
  sent_ip VARCHAR(64) NOT NULL DEFAULT '',
  sent_user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auth_email_otp_user (user_id, purpose, expires_at),
  KEY idx_auth_email_otp_active (user_id, consumed_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_trusted_devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  selector CHAR(24) NOT NULL,
  validator_hash CHAR(64) NOT NULL,
  fingerprint_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_ip VARCHAR(64) NOT NULL DEFAULT '',
  created_user_agent VARCHAR(255) NOT NULL DEFAULT '',
  last_ip VARCHAR(64) NOT NULL DEFAULT '',
  last_user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL DEFAULT NULL,
  revoked_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ux_auth_trusted_selector (selector),
  KEY idx_auth_trusted_user (user_id, expires_at),
  KEY idx_auth_trusted_active (user_id, revoked_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_backup_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  used_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auth_backup_user (user_id, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
