-- Career Compass additional prototype features.
-- New database: run AFTER schema.sql. Existing database: use scripts/migrate.php.
-- Do not drop tables or erase any learner data.

CREATE TABLE IF NOT EXISTS assessment_responses (
  assessment_id INT UNSIGNED NOT NULL PRIMARY KEY,
  instrument_version VARCHAR(80) NOT NULL,
  language_code VARCHAR(5) NOT NULL DEFAULT 'en',
  responses LONGTEXT NOT NULL,
  CONSTRAINT fk_cc_responses_assessment FOREIGN KEY (assessment_id)
    REFERENCES assessments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS profile_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  payload LONGTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cc_profile_versions_user (user_id, id),
  CONSTRAINT fk_cc_profile_versions_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recommendation_runs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assessment_id INT UNSIGNED NOT NULL,
  profile_version_id INT UNSIGNED NOT NULL,
  method_version VARCHAR(80) NOT NULL,
  payload LONGTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cc_runs_user (user_id, id),
  CONSTRAINT fk_cc_runs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_cc_runs_assessment FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
  CONSTRAINT fk_cc_runs_profile FOREIGN KEY (profile_version_id) REFERENCES profile_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recommendation_details (
  recommendation_id INT UNSIGNED NOT NULL,
  run_id INT UNSIGNED NOT NULL,
  payload LONGTEXT NOT NULL,
  PRIMARY KEY (recommendation_id,run_id),
  INDEX idx_cc_details_run (run_id),
  CONSTRAINT fk_cc_details_recommendation FOREIGN KEY (recommendation_id)
    REFERENCES recommendations(id) ON DELETE CASCADE,
  CONSTRAINT fk_cc_details_run FOREIGN KEY (run_id)
    REFERENCES recommendation_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS career_plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  career_title VARCHAR(200) NOT NULL,
  goal TEXT NOT NULL,
  roadmap LONGTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cc_plans_user (user_id,id),
  CONSTRAINT fk_cc_plans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roadmap_milestones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  plan_id INT UNSIGNED NOT NULL,
  title VARCHAR(250) NOT NULL,
  due_date DATE DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  INDEX idx_cc_milestones_plan (plan_id),
  CONSTRAINT fk_cc_milestones_plan FOREIGN KEY (plan_id)
    REFERENCES career_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
