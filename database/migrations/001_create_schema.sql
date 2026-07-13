CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS prizes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  description TEXT NOT NULL,
  instructions TEXT NOT NULL DEFAULT '',
  cover_path TEXT NOT NULL,
  rarity TEXT NOT NULL,
  weight INTEGER NOT NULL DEFAULT 1,
  max_quantity INTEGER NOT NULL DEFAULT 1,
  won_count INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  token_hash TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL CHECK (status IN ('Active', 'Used', 'Expired', 'Revoked')),
  expires_at TEXT NULL,
  used_at TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS draws (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  draw_id TEXT NOT NULL UNIQUE,
  token_id INTEGER NOT NULL UNIQUE,
  prize_id INTEGER NOT NULL,
  token_mask TEXT NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (token_id) REFERENCES tokens(id),
  FOREIGN KEY (prize_id) REFERENCES prizes(id)
);

CREATE INDEX IF NOT EXISTS idx_tokens_status ON tokens(status);
CREATE INDEX IF NOT EXISTS idx_draws_created_at ON draws(created_at);
