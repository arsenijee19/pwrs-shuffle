CREATE TABLE IF NOT EXISTS mystery_box_fulfillments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id TEXT NOT NULL UNIQUE,
  recipient_email TEXT NOT NULL,
  recipient_name TEXT NOT NULL DEFAULT '',
  requested_quantity INTEGER NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('Pending', 'Processing', 'Partial', 'Completed', 'NeedsReview')),
  sent_quantity INTEGER NOT NULL DEFAULT 0,
  error_code TEXT NULL,
  error_message TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  completed_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS mystery_box_fulfillment_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  fulfillment_id INTEGER NOT NULL,
  sequence_number INTEGER NOT NULL,
  token_id INTEGER NULL,
  status TEXT NOT NULL CHECK (status IN ('Pending', 'Sending', 'Sent', 'Failed', 'NeedsReview')),
  error_code TEXT NULL,
  error_message TEXT NULL,
  started_at TEXT NULL,
  sent_at TEXT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY (fulfillment_id) REFERENCES mystery_box_fulfillments(id) ON DELETE CASCADE,
  FOREIGN KEY (token_id) REFERENCES tokens(id),
  UNIQUE (fulfillment_id, sequence_number)
);

CREATE INDEX IF NOT EXISTS idx_mystery_box_fulfillments_status
  ON mystery_box_fulfillments(status);
CREATE INDEX IF NOT EXISTS idx_mystery_box_fulfillments_created_at
  ON mystery_box_fulfillments(created_at);
CREATE INDEX IF NOT EXISTS idx_mystery_box_fulfillment_items_status
  ON mystery_box_fulfillment_items(status);
