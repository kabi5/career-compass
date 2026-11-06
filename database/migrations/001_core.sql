CREATE TABLE IF NOT EXISTS institutions (
 id VARCHAR(64) PRIMARY KEY, name VARCHAR(180) NOT NULL, payload LONGTEXT NOT NULL,
 verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified', source_url VARCHAR(500), last_verified DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS programmes (
 id VARCHAR(64) PRIMARY KEY, institution_id VARCHAR(64), title VARCHAR(200) NOT NULL, payload LONGTEXT NOT NULL,
 verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified', source_url VARCHAR(500), last_verified DATE,
 FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE RESTRICT, INDEX idx_programme_status(verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS admission_requirements (
 programme_id VARCHAR(64) PRIMARY KEY, grading_system VARCHAR(30) NOT NULL, rules_json LONGTEXT NOT NULL,
 FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS scholarships (
 id VARCHAR(64) PRIMARY KEY, title VARCHAR(200) NOT NULL, provider VARCHAR(180) NOT NULL, payload LONGTEXT NOT NULL,
 verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified', source_url VARCHAR(500), last_verified DATE,
 INDEX idx_scholarship_status(verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS career_catalogue (
 id VARCHAR(64) PRIMARY KEY, title VARCHAR(200) NOT NULL, payload LONGTEXT NOT NULL,
 verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified', source_url VARCHAR(500), last_verified DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS profile_versions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
 payload LONGTEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_profile_version(user_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS recommendation_runs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
 assessment_id INT UNSIGNED NOT NULL, profile_version_id INT UNSIGNED NOT NULL,
 method_version VARCHAR(80) NOT NULL, payload LONGTEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
 FOREIGN KEY (profile_version_id) REFERENCES profile_versions(id) ON DELETE CASCADE,
 INDEX idx_run_user(user_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS recommendation_details (
 recommendation_id INT UNSIGNED PRIMARY KEY, run_id INT UNSIGNED NOT NULL, payload LONGTEXT NOT NULL,
 FOREIGN KEY (recommendation_id) REFERENCES recommendations(id) ON DELETE CASCADE,
 FOREIGN KEY (run_id) REFERENCES recommendation_runs(id) ON DELETE CASCADE, INDEX idx_detail_run(run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS assessment_responses (
 assessment_id INT UNSIGNED PRIMARY KEY, instrument_version VARCHAR(80) NOT NULL,
 language_code VARCHAR(5) NOT NULL, responses LONGTEXT NOT NULL,
 FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS recommendation_feedback (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, recommendation_id INT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NOT NULL, comment VARCHAR(1000) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY idx_feedback_once(user_id,recommendation_id), CHECK (rating BETWEEN 1 AND 5),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (recommendation_id) REFERENCES recommendations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS saved_items (
 user_id INT UNSIGNED NOT NULL, item_type ENUM('career','programme','scholarship') NOT NULL, item_id VARCHAR(200) NOT NULL,
 title VARCHAR(200) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(user_id,item_type,item_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS career_plans (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, career_title VARCHAR(200) NOT NULL,
 goal TEXT NOT NULL, roadmap LONGTEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_plan_user(user_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS roadmap_milestones (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, plan_id INT UNSIGNED NOT NULL, title VARCHAR(250) NOT NULL,
 due_date DATE NULL, completed_at DATETIME NULL,
 FOREIGN KEY (plan_id) REFERENCES career_plans(id) ON DELETE CASCADE, INDEX idx_milestone_plan(plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
