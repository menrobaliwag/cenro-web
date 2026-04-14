-- Eco-Police / Monitoring attendance tables
-- Run once on your target database.

CREATE TABLE IF NOT EXISTS eco_attendance_sheets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attendance_date DATE NOT NULL,
  category VARCHAR(40) NOT NULL,
  created_by INT(11) DEFAULT NULL,
  updated_by INT(11) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_eco_attendance_sheet_date_category (attendance_date, category),
  KEY idx_eco_attendance_sheet_category_date (category, attendance_date),
  KEY idx_eco_attendance_sheet_created_by (created_by),
  KEY idx_eco_attendance_sheet_updated_by (updated_by),
  CONSTRAINT fk_eco_attendance_sheet_created_by
    FOREIGN KEY (created_by) REFERENCES user_form(id) ON DELETE SET NULL,
  CONSTRAINT fk_eco_attendance_sheet_updated_by
    FOREIGN KEY (updated_by) REFERENCES user_form(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS eco_attendance_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sheet_id BIGINT UNSIGNED NOT NULL,
  employee_user_id INT(11) DEFAULT NULL,
  employee_name VARCHAR(190) NOT NULL,
  attendance_status VARCHAR(20) NOT NULL DEFAULT 'absent',
  note_reason VARCHAR(255) DEFAULT NULL,
  sort_order INT(11) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_eco_attendance_entries_sheet (sheet_id),
  KEY idx_eco_attendance_entries_employee (employee_user_id),
  KEY idx_eco_attendance_entries_status (attendance_status),
  CONSTRAINT fk_eco_attendance_entries_sheet
    FOREIGN KEY (sheet_id) REFERENCES eco_attendance_sheets(id) ON DELETE CASCADE,
  CONSTRAINT fk_eco_attendance_entries_employee
    FOREIGN KEY (employee_user_id) REFERENCES user_form(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
