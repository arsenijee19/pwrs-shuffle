CREATE TABLE IF NOT EXISTS email_deliveries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  token_id INTEGER NULL,
  recipient_email TEXT NOT NULL,
  recipient_name TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL CHECK (status IN ('Sent', 'Failed')),
  error_message TEXT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (token_id) REFERENCES tokens(id)
);

CREATE INDEX IF NOT EXISTS idx_email_deliveries_created_at ON email_deliveries(created_at);
CREATE INDEX IF NOT EXISTS idx_email_deliveries_status ON email_deliveries(status);
