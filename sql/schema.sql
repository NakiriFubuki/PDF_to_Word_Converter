CREATE DATABASE IF NOT EXISTS pdf_to_word
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pdf_to_word;

CREATE TABLE IF NOT EXISTS conversions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(64) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  direction ENUM('pdf_to_word', 'word_to_pdf') NOT NULL DEFAULT 'pdf_to_word',
  stored_pdf VARCHAR(255) NOT NULL,
  stored_docx VARCHAR(255) DEFAULT NULL,
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  page_count INT UNSIGNED DEFAULT NULL,
  status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  error_message VARCHAR(500) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  INDEX idx_session (session_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
