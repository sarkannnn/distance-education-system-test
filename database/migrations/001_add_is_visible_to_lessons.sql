-- Archive: Add is_visible column to live_classes and archived_lessons
-- Created: 2026-03-19

-- Adding is_visible to archived_lessons
ALTER TABLE archived_lessons ADD COLUMN IF NOT EXISTS is_visible TINYINT(1) DEFAULT 1;

-- Adding is_visible to live_classes
ALTER TABLE live_classes ADD COLUMN IF NOT EXISTS is_visible TINYINT(1) DEFAULT 1;

-- Note: MySQL/MariaDB only supports ADD COLUMN IF NOT EXISTS in newer versions.
-- If it fails, use:
-- ALTER TABLE archived_lessons ADD COLUMN is_visible TINYINT(1) DEFAULT 1;
-- ALTER TABLE live_classes ADD COLUMN is_visible TINYINT(1) DEFAULT 1;
