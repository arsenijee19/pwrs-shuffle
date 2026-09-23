CREATE TABLE IF NOT EXISTS reward_deliveries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  draw_id TEXT NOT NULL UNIQUE,
  prize_name TEXT NOT NULL,
  recipient_email TEXT NOT NULL,
  requested_quantity INTEGER NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('Pending', 'Sending', 'Sent', 'Failed', 'NeedsReview')) DEFAULT 'Pending',
  error_message TEXT NULL,
  created_at TEXT NOT NULL,
  sent_at TEXT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY (draw_id) REFERENCES draws(draw_id)
);

CREATE TABLE IF NOT EXISTS reward_delivery_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  delivery_id INTEGER NOT NULL,
  sequence_number INTEGER NOT NULL,
  token_id INTEGER NOT NULL,
  link_ciphertext TEXT NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (delivery_id) REFERENCES reward_deliveries(id) ON DELETE CASCADE,
  FOREIGN KEY (token_id) REFERENCES tokens(id),
  UNIQUE(delivery_id, sequence_number),
  UNIQUE(delivery_id, token_id)
);

CREATE INDEX IF NOT EXISTS idx_reward_deliveries_status
  ON reward_deliveries(status, created_at);
