-- MySQL 8.0+

CREATE DATABASE IF NOT EXISTS vormipaevik
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE vormipaevik;

-- users
CREATE TABLE users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role            ENUM('ADMIN_TEACHER','TEACHER','STUDENT') NOT NULL,
  name            VARCHAR(100) NOT NULL,
  username        VARCHAR(120) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_role (role),
  KEY idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `groups` (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(20) NOT NULL,
  name        VARCHAR(120) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_groups_code (code),
  KEY idx_groups_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE group_students (
  group_id          INT UNSIGNED NOT NULL,
  student_user_id   INT UNSIGNED NOT NULL,
  joined_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, student_user_id),
  KEY idx_gs_student (student_user_id),
  CONSTRAINT fk_gs_group
    FOREIGN KEY (group_id) REFERENCES `groups`(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_gs_student
    FOREIGN KEY (student_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE teacher_group_access (
  teacher_user_id     INT UNSIGNED NOT NULL,
  group_id            INT UNSIGNED NOT NULL,
  granted_by_user_id  INT UNSIGNED NOT NULL,
  granted_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (teacher_user_id, group_id),
  KEY idx_tga_group (group_id),
  KEY idx_tga_granted_by (granted_by_user_id),
  CONSTRAINT fk_tga_teacher
    FOREIGN KEY (teacher_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_tga_group
    FOREIGN KEY (group_id) REFERENCES `groups`(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_tga_granted_by
    FOREIGN KEY (granted_by_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activities (
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

CREATE TABLE entries (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_user_id  INT UNSIGNED NOT NULL,
  activity_id      INT UNSIGNED NULL,
  entry_date       DATE NOT NULL,
  weight_kg        DECIMAL(5,2) NULL,
  pushups          INT NULL,
  note             VARCHAR(300) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entries_student_date (student_user_id, entry_date),
  KEY idx_entries_student_date (student_user_id, entry_date),
  KEY idx_entries_date (entry_date),
  KEY idx_entries_activity_date (activity_id, entry_date),
  CONSTRAINT fk_entries_student
    FOREIGN KEY (student_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entries_activity
    FOREIGN KEY (activity_id) REFERENCES activities(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_entries_pushups CHECK (pushups IS NULL OR (pushups >= 0 AND pushups <= 300)),
  CONSTRAINT chk_entries_weight CHECK (weight_kg IS NULL OR (weight_kg >= 20 AND weight_kg <= 300))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_feedback (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entry_id      INT UNSIGNED NOT NULL,
  provider      VARCHAR(50) NOT NULL,
  model         VARCHAR(80) NOT NULL,
  feedback_text TEXT NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feedback_entry (entry_id),
  CONSTRAINT fk_feedback_entry
    FOREIGN KEY (entry_id) REFERENCES entries(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id INT UNSIGNED NULL,
  action        VARCHAR(60) NOT NULL,
  entity_type   VARCHAR(40) NULL,
  entity_id     BIGINT UNSIGNED NULL,
  ip_address    VARCHAR(45) NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_actor_time (actor_user_id, created_at),
  KEY idx_audit_action_time (action, created_at),
  CONSTRAINT fk_audit_actor
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip         VARCHAR(45) NOT NULL,
  username   VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts_ip_user (ip, username, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE teacher_notes (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  teacher_user_id  INT UNSIGNED NOT NULL,
  student_user_id  INT UNSIGNED NOT NULL,
  note_text        VARCHAR(500) NOT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tn_student_time (student_user_id, created_at),
  CONSTRAINT fk_tn_teacher
    FOREIGN KEY (teacher_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_tn_student
    FOREIGN KEY (student_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `groups` (code, name) VALUES
('ITA25', 'ITA 2025'),
('ITS25', 'ITS 2025');

INSERT INTO activities (name, created_by_user_id) VALUES
('Jooks', NULL),
('Kõnd', NULL),
('Rattasõit', NULL),
('Jõutreening', NULL);