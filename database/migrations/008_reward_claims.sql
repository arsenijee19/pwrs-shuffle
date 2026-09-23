CREATE TABLE IF NOT EXISTS reward_claims (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  draw_id TEXT NOT NULL UNIQUE,
  prize_name TEXT NOT NULL,
  recipient_email TEXT NOT NULL,
  recipient_phone TEXT NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('New', 'InProgress', 'Delivered', 'Cancelled')) DEFAULT 'New',
  notification_status TEXT NOT NULL CHECK (notification_status IN ('Pending', 'Sending', 'Sent', 'Failed', 'NeedsReview')) DEFAULT 'Pending',
  notification_attempts INTEGER NOT NULL DEFAULT 0,
  notification_lease_until TEXT NULL,
  notification_worker TEXT NULL,
  provider_message_id TEXT NULL,
  notification_error TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  completed_at TEXT NULL,
  FOREIGN KEY (draw_id) REFERENCES draws(draw_id)
);

CREATE INDEX IF NOT EXISTS idx_reward_claims_notification
  ON reward_claims(notification_status, notification_lease_until, created_at);
CREATE INDEX IF NOT EXISTS idx_reward_claims_status
  ON reward_claims(status, created_at);
