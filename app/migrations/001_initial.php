<?php
declare(strict_types=1);

$t = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$ts = "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP";

return [
"CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NULL
) $t",

"CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT \x27viewer\x27,
  permissions TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT \x27active\x27,
  is_owner TINYINT(1) NOT NULL DEFAULT 0,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  last_active_at DATETIME NULL,
  $ts
) $t",

"CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_time (email, created_at),
  INDEX idx_ip_time (ip, created_at)
) $t",

"CREATE TABLE IF NOT EXISTS activity (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  module VARCHAR(40) NOT NULL DEFAULT \x27\x27,
  record_id INT UNSIGNED NULL,
  summary VARCHAR(255) NOT NULL DEFAULT \x27\x27,
  ip VARCHAR(45) NOT NULL DEFAULT \x27\x27,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  INDEX idx_user (user_id)
) $t",

"CREATE TABLE IF NOT EXISTS employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_no VARCHAR(40) NULL,
  name VARCHAR(150) NOT NULL,
  department VARCHAR(100) NULL,
  designation VARCHAR(120) NULL,
  nationality VARCHAR(80) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  joining_date DATE NULL,
  qid VARCHAR(40) NULL,
  qid_expiry DATE NULL,
  passport VARCHAR(40) NULL,
  passport_expiry DATE NULL,
  visa_expiry DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT \x27active\x27,
  notes TEXT NULL,
  $ts,
  INDEX idx_name (name),
  INDEX idx_dept (department)
) $t",

"CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(100) NULL,
  owner VARCHAR(120) NULL,
  reference VARCHAR(120) NULL,
  issue_date DATE NULL,
  expiry_date DATE NULL,
  notes TEXT NULL,
  $ts,
  INDEX idx_expiry (expiry_date)
) $t",

"CREATE TABLE IF NOT EXISTS emp_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NULL,
  doc_type VARCHAR(100) NOT NULL,
  reference VARCHAR(120) NULL,
  issue_date DATE NULL,
  expiry_date DATE NULL,
  notes TEXT NULL,
  $ts,
  INDEX idx_emp (employee_id),
  INDEX idx_expiry (expiry_date)
) $t",

"CREATE TABLE IF NOT EXISTS assets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_type VARCHAR(40) NOT NULL DEFAULT \x27Vehicle\x27,
  item VARCHAR(150) NOT NULL,
  details VARCHAR(255) NULL,
  reference VARCHAR(120) NULL,
  assigned_to VARCHAR(150) NULL,
  registration_expiry DATE NULL,
  coverage_expiry DATE NULL,
  purchase_date DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT \x27in_use\x27,
  notes TEXT NULL,
  $ts
) $t",

"CREATE TABLE IF NOT EXISTS tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  owner VARCHAR(120) NULL,
  priority VARCHAR(20) NOT NULL DEFAULT \x27medium\x27,
  deadline DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT \x27open\x27,
  notes TEXT NULL,
  $ts,
  INDEX idx_deadline (deadline)
) $t",

"CREATE TABLE IF NOT EXISTS leave_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NULL,
  leave_type VARCHAR(30) NOT NULL DEFAULT \x27annual\x27,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  days DECIMAL(5,1) NOT NULL DEFAULT 0,
  reason TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT \x27pending\x27,
  decided_by INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  $ts,
  INDEX idx_emp (employee_id),
  INDEX idx_dates (start_date, end_date)
) $t",

"CREATE TABLE IF NOT EXISTS attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  check_in TIME NULL,
  check_out TIME NULL,
  status VARCHAR(20) NOT NULL DEFAULT \x27present\x27,
  notes VARCHAR(255) NULL,
  $ts,
  UNIQUE KEY uq_emp_date (employee_id, work_date),
  INDEX idx_date (work_date)
) $t",

"CREATE TABLE IF NOT EXISTS candidates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  role VARCHAR(150) NOT NULL,
  experience DECIMAL(4,1) NOT NULL DEFAULT 0,
  keywords TEXT NULL,
  cv_text MEDIUMTEXT NULL,
  file_name VARCHAR(190) NULL,
  score INT NOT NULL DEFAULT 0,
  matched TEXT NULL,
  missing TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT \x27new\x27,
  ai_summary MEDIUMTEXT NULL,
  ai_score INT NULL,
  created_by INT UNSIGNED NULL,
  $ts
) $t",

"CREATE TABLE IF NOT EXISTS tools (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(10) NOT NULL DEFAULT \x27app\x27,
  name VARCHAR(100) NOT NULL,
  category VARCHAR(80) NULL,
  url VARCHAR(500) NOT NULL,
  description VARCHAR(300) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  $ts
) $t",

"CREATE TABLE IF NOT EXISTS ai_usage (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  kind VARCHAR(30) NOT NULL DEFAULT \x27chat\x27,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_time (user_id, created_at)
) $t",
];
