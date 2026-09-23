CREATE TABLE IF NOT EXISTS reward_claim_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  claim_id INTEGER NOT NULL,
  admin_id INTEGER NULL,
  event_name TEXT NOT NULL,
  old_status TEXT NULL,
  new_status TEXT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (claim_id) REFERENCES reward_claims(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES admins(id)
);

CREATE INDEX IF NOT EXISTS idx_reward_claim_events_claim_created
  ON reward_claim_events(claim_id, created_at);
