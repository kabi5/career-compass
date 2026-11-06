-- Education reference labels include long descriptions. Preserve them without truncation.
ALTER TABLE recommendations MODIFY COLUMN education_level TEXT NULL;
ALTER TABLE recommendations MODIFY COLUMN career_title VARCHAR(200) NOT NULL;
