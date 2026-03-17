USE vormipaevik;

CREATE TABLE IF NOT EXISTS activities (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name               VARCHAR(100) NOT NULL,
  created_by_user_id INT UNSIGNED NULL,
  is_active          TINYINT(1) NOT NULL DEFAULT 1,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_activities_name (name),
  KEY idx_activities_active_name (is_active, name),
  CONSTRAINT fk_activities_created_by
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- entries.activity_id (kui puudub)
SET @has_activity_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'entries'
    AND COLUMN_NAME = 'activity_id'
);
SET @sql_add_col := IF(
  @has_activity_col = 0,
  'ALTER TABLE entries ADD COLUMN activity_id INT UNSIGNED NULL AFTER student_user_id',
  'SELECT 1'
);
PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

-- index entries(activity_id, entry_date) (kui puudub)
SET @has_activity_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'entries'
    AND INDEX_NAME = 'idx_entries_activity_date'
);
SET @sql_add_idx := IF(
  @has_activity_idx = 0,
  'CREATE INDEX idx_entries_activity_date ON entries (activity_id, entry_date)',
  'SELECT 1'
);
PREPARE stmt_add_idx FROM @sql_add_idx;
EXECUTE stmt_add_idx;
DEALLOCATE PREPARE stmt_add_idx;

-- FK entries.activity_id -> activities.id (kui puudub)
SET @has_activity_fk := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_entries_activity'
);
SET @sql_add_fk := IF(
  @has_activity_fk = 0,
  'ALTER TABLE entries ADD CONSTRAINT fk_entries_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt_add_fk FROM @sql_add_fk;
EXECUTE stmt_add_fk;
DEALLOCATE PREPARE stmt_add_fk;

-- Vaiketegevused
INSERT IGNORE INTO activities (name, created_by_user_id) VALUES
('Jooks', NULL),
('Kõnd', NULL),
('Rattasõit', NULL),
('Jõutreening', NULL),
('Ujumine', NULL);
