<?php
declare(strict_types=1);

return [
    "CREATE TABLE IF NOT EXISTS biometric_punches (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      source_id BIGINT UNSIGNED NOT NULL,
      employee_code VARCHAR(40) NOT NULL,
      employee_id INT UNSIGNED NULL,
      punch_time DATETIME NOT NULL,
      punch_state VARCHAR(20) NULL,
      verify_type VARCHAR(20) NULL,
      terminal_sn VARCHAR(40) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_source_id (source_id),
      INDEX idx_employee_time (employee_id, punch_time),
      INDEX idx_unmatched (employee_id, employee_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
