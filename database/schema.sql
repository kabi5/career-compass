-- Fresh MySQL 8.x schema. For an existing database, run scripts/migrate.php
-- after importing or deploying this version. Never erase existing student data.
CREATE DATABASE IF NOT EXISTS career_guidance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE career_guidance;

CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(120) NOT NULL,
 email VARCHAR(120) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('learner','teacher','admin') NOT NULL DEFAULT 'learner',
 grade VARCHAR(10) DEFAULT NULL,
 school VARCHAR(150) DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS learner_profiles (
 user_id INT UNSIGNED NOT NULL PRIMARY KEY,
 subjects TEXT, maths_mark DECIMAL(5,2), science_mark DECIMAL(5,2),
 english_mark DECIMAL(5,2), other_marks TEXT, aspirations TEXT,
 qualification_type VARCHAR(20) DEFAULT NULL,
 qualification_year SMALLINT UNSIGNED DEFAULT NULL,
 subject_results LONGTEXT DEFAULT NULL,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessments (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 riasec_scores TEXT NOT NULL,
 top_interest VARCHAR(20) NOT NULL,
 taken_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_assessment_user_time (user_id,taken_at),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recommendations (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 career_title VARCHAR(200) NOT NULL,
 -- NULL means the evidence is insufficient for a defensible overall percentage.
 match_score DECIMAL(5,2) DEFAULT NULL,
 ranking_score DECIMAL(5,2) DEFAULT NULL,
 data_coverage DECIMAL(5,2) DEFAULT NULL,
 score_status VARCHAR(30) DEFAULT NULL,
 score_version VARCHAR(40) DEFAULT NULL,
 score_details LONGTEXT DEFAULT NULL,
 eligibility_status VARCHAR(50) DEFAULT NULL,
 explanation TEXT,
 recommended_subjects TEXT,
 pathway TEXT,
 holland_code VARCHAR(10) DEFAULT NULL,
 education_level TEXT DEFAULT NULL,
 local_programmes LONGTEXT DEFAULT NULL,
 generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_recommendation_user_score (user_id,match_score),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS careers (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(150) NOT NULL,
 riasec_code VARCHAR(6),required_subjects TEXT,skills TEXT,
 demand_level ENUM('low','medium','high') DEFAULT 'medium',description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_questions (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 question TEXT NOT NULL,
 riasec_dimension CHAR(1) NOT NULL,
 weight TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Original assessment table was empty. Seed 24 unvalidated exploratory items.
-- Explicit IDs ensure safe re-import. Admins should review all items with
-- educators before using responses for research or counselling.
INSERT IGNORE INTO assessment_questions (id,question,riasec_dimension,weight) VALUES
(1,'I enjoy fixing things or working with tools.','R',1),
(2,'I like building practical objects or models.','R',1),
(3,'I would enjoy working outdoors on hands-on projects.','R',1),
(4,'I like discovering how machines work.','R',1),
(5,'I enjoy solving maths and science problems.','I',1),
(6,'I like doing experiments to find answers.','I',1),
(7,'I enjoy researching topics that make me curious.','I',1),
(8,'I like analysing information to understand patterns.','I',1),
(9,'I enjoy drawing, writing or making creative things.','A',1),
(10,'I like thinking of original ideas.','A',1),
(11,'I would enjoy designing a poster, website or product.','A',1),
(12,'I enjoy expressing myself through art, music or stories.','A',1),
(13,'I enjoy explaining things to other learners.','S',1),
(14,'I like helping people solve personal problems.','S',1),
(15,'I enjoy working with and supporting a team.','S',1),
(16,'I would enjoy teaching or caring for people.','S',1),
(17,'I like leading group activities or projects.','E',1),
(18,'I enjoy sharing ideas to persuade others.','E',1),
(19,'I would like to start a small business someday.','E',1),
(20,'I enjoy making decisions and organising activities.','E',1),
(21,'I enjoy keeping records organised and accurate.','C',1),
(22,'I like following a clear plan or process.','C',1),
(23,'I enjoy checking details for mistakes.','C',1),
(24,'I would enjoy organising information in tables or files.','C',1);

-- Additional functional modules: run history, evidence, roadmaps.
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

