-- Migration: Add is_visible column to webinars table
-- Run this on the webinar database (not the main distance-education DB)

ALTER TABLE webinars ADD COLUMN IF NOT EXISTS is_visible TINYINT(1) DEFAULT 1;

-- For MySQL versions that don't support IF NOT EXISTS in ALTER TABLE:
-- ALTER TABLE webinars ADD COLUMN is_visible TINYINT(1) DEFAULT 1;
