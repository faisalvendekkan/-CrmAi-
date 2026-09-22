<?php
declare(strict_types=1);

$t = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

return [
    "CREATE TABLE IF NOT EXISTS workspace_items (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      kind VARCHAR(20) NOT NULL DEFAULT 'note',
      title VARCHAR(180) NOT NULL,
      category VARCHAR(100) NULL,
      content MEDIUMTEXT NOT NULL,
      is_pinned TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_workspace_user_kind (user_id, kind),
      INDEX idx_workspace_user_pin (user_id, is_pinned)
    ) $t"
];
