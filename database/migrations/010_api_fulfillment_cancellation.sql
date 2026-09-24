ALTER TABLE mystery_box_fulfillments ADD COLUMN cancelled_at TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_mystery_box_fulfillments_cancelled_at
  ON mystery_box_fulfillments(cancelled_at);
