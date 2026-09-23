CREATE TABLE IF NOT EXISTS discount_codes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  percentage INTEGER NOT NULL CHECK (percentage BETWEEN 1 AND 100),
  code_hash TEXT NOT NULL UNIQUE,
  code_ciphertext TEXT NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('Available', 'Assigned')) DEFAULT 'Available',
  assigned_draw_id TEXT NULL UNIQUE,
  assigned_at TEXT NULL,
  created_by INTEGER NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (assigned_draw_id) REFERENCES draws(draw_id),
  FOREIGN KEY (created_by) REFERENCES admins(id)
);

CREATE INDEX IF NOT EXISTS idx_discount_codes_available
  ON discount_codes(percentage, status);
CREATE INDEX IF NOT EXISTS idx_discount_codes_assigned_draw
  ON discount_codes(assigned_draw_id);
