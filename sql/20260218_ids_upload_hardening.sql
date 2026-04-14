-- Harden eco_police ids upload table linkage (if stored elsewhere, adjust accordingly)
ALTER TABLE violations
  ADD COLUMN IF NOT EXISTS uploaded_by INT UNSIGNED NULL,
  ADD INDEX idx_violations_uploaded_at (date_created),
  ADD CONSTRAINT fk_violations_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES user_form(id) ON DELETE SET NULL;
