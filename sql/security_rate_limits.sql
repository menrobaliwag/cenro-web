CREATE TABLE IF NOT EXISTS security_rate_limits (
  rate_key CHAR(64) NOT NULL,
  bucket_start INT UNSIGNED NOT NULL,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_ip VARCHAR(64) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (rate_key, bucket_start),
  KEY idx_security_rl_updated (updated_at),
  KEY idx_security_rl_bucket (bucket_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
