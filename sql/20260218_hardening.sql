-- Security/DB hardening migration
-- Run with a privileged user once, then deploy app users with no CREATE/ALTER rights.

-- mbcurp_files
ALTER TABLE mbcurp_files
  ADD INDEX idx_mbcurp_uploaded_at (uploaded_at),
  ADD INDEX idx_mbcurp_folder_year_deleted (folder_key, file_year, is_deleted, uploaded_at),
  ADD CONSTRAINT fk_mbcurp_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES user_form(id) ON DELETE SET NULL;

-- parks_files
ALTER TABLE parks_files
  ADD INDEX idx_parks_uploaded_at (uploaded_at),
  ADD INDEX idx_parks_cat_year_deleted (category_key, file_year, is_deleted, uploaded_at),
  ADD CONSTRAINT fk_parks_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES user_form(id) ON DELETE SET NULL;

-- monitoring_files
ALTER TABLE monitoring_files
  ADD INDEX idx_monitoring_uploaded_at (uploaded_at),
  ADD INDEX idx_monitoring_cat_year_deleted (category_key, file_year, is_deleted, uploaded_at),
  ADD CONSTRAINT fk_monitoring_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES user_form(id) ON DELETE SET NULL;

-- palitbasura_records
ALTER TABLE palitbasura_records
  ADD INDEX idx_pb_record_date (record_date),
  ADD INDEX idx_pb_category_archived (category, archived, record_date);

