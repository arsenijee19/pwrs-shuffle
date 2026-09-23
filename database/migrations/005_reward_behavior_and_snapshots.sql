ALTER TABLE prizes ADD COLUMN bonus_box_count INTEGER NOT NULL DEFAULT 0;
ALTER TABLE prizes ADD COLUMN discount_percent INTEGER NULL;
ALTER TABLE prizes ADD COLUMN manual_claim INTEGER NOT NULL DEFAULT 0;

ALTER TABLE draws ADD COLUMN snapshot_name TEXT NULL;
ALTER TABLE draws ADD COLUMN snapshot_description TEXT NULL;
ALTER TABLE draws ADD COLUMN snapshot_instructions TEXT NULL;
ALTER TABLE draws ADD COLUMN snapshot_cover_path TEXT NULL;
ALTER TABLE draws ADD COLUMN snapshot_rarity TEXT NULL;
ALTER TABLE draws ADD COLUMN snapshot_bonus_box_count INTEGER NULL;
ALTER TABLE draws ADD COLUMN snapshot_discount_percent INTEGER NULL;
ALTER TABLE draws ADD COLUMN snapshot_manual_claim INTEGER NULL;
ALTER TABLE draws ADD COLUMN client_fingerprint TEXT NULL;
ALTER TABLE draws ADD COLUMN anti_repeat_applied INTEGER NOT NULL DEFAULT 0;
ALTER TABLE draws ADD COLUMN anti_repeat_window_minutes INTEGER NULL;

CREATE TABLE IF NOT EXISTS prize_change_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  prize_id INTEGER NOT NULL,
  field_name TEXT NOT NULL,
  old_value TEXT NULL,
  new_value TEXT NULL,
  admin_id INTEGER NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (prize_id) REFERENCES prizes(id),
  FOREIGN KEY (admin_id) REFERENCES admins(id)
);

CREATE INDEX IF NOT EXISTS idx_draws_client_fingerprint_created
  ON draws(client_fingerprint, created_at);
CREATE INDEX IF NOT EXISTS idx_prize_change_log_prize_created
  ON prize_change_log(prize_id, created_at);
