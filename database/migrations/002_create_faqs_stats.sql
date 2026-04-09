-- FAQ Click Statistics Table
-- Used for tracking which questions are most popular to optimize the local KB

CREATE TABLE IF NOT EXISTS faq_click_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faq_question VARCHAR(500) NOT NULL,
    click_count INT DEFAULT 1,
    last_clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_question (faq_question(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure the faqs table has a keywords column if it doesn't
SET @dbname = DATABASE();
SET @tablename = "faqs";
SET @columnname = "keywords";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE faqs ADD COLUMN keywords TEXT AFTER answer"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
