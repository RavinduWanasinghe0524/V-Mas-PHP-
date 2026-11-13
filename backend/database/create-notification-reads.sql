-- Add per-user notification read state to an existing V-MAS database.
CREATE TABLE IF NOT EXISTS notification_reads (
  notification_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id, user_id),
  INDEX idx_notification_reads_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
