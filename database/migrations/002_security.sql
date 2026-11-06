-- Additive security and educator access tables. Existing accounts are preserved.
CREATE TABLE IF NOT EXISTS schools (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 code VARCHAR(40) NOT NULL UNIQUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS user_security (
 user_id INT UNSIGNED PRIMARY KEY,
 school_id INT UNSIGNED NULL,
 email_verified_at DATETIME NULL,
 auth_version INT UNSIGNED NOT NULL DEFAULT 1,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
 INDEX idx_security_school(school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS rate_limits (
 bucket_hash CHAR(64) PRIMARY KEY,
 window_start BIGINT UNSIGNED NOT NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 1,
 INDEX idx_rate_window(window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS account_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 purpose ENUM('reset','verify') NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_tokens_user(user_id,purpose),
 INDEX idx_tokens_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS consent_records (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 purpose VARCHAR(60) NOT NULL,
 policy_version VARCHAR(30) NOT NULL,
 granted_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_consent_user(user_id,purpose,revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS teacher_grants (
 learner_id INT UNSIGNED NOT NULL,
 teacher_id INT UNSIGNED NOT NULL,
 granted_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 PRIMARY KEY(learner_id,teacher_id),
 FOREIGN KEY(learner_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_grants_teacher(teacher_id,revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS counselling_notes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 teacher_id INT UNSIGNED NULL,
 learner_id INT UNSIGNED NOT NULL,
 school_id INT UNSIGNED NOT NULL,
 note TEXT NOT NULL,
 next_action VARCHAR(500) NOT NULL,
 follow_up_on DATE NULL,
 status ENUM('open','completed') NOT NULL DEFAULT 'open',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(learner_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(school_id) REFERENCES schools(id) ON DELETE CASCADE,
 INDEX idx_notes_access(teacher_id,learner_id,school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_id INT UNSIGNED NULL,
 event VARCHAR(80) NOT NULL,
 target_type VARCHAR(40) NOT NULL,
 target_id VARCHAR(80) NULL,
 details TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_audit_time(created_at),
 INDEX idx_audit_actor(actor_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS content_submissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 entity_type ENUM('institutions','programmes','scholarships','career_catalogue') NOT NULL,
 record_id VARCHAR(64) NOT NULL,
 payload LONGTEXT NOT NULL,
 submitted_by INT UNSIGNED NULL,
 reviewed_by INT UNSIGNED NULL,
 review_note VARCHAR(1000) NULL,
 status ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 FOREIGN KEY(submitted_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_submissions_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
