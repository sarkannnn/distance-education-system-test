-- Migration: Add egress_id column to live_classes and egress_failures table
-- Run on the main distance-education DB (distant_tehsil)

-- Stores the active LiveKit Egress ID so recording can be stopped and status-checked later
ALTER TABLE live_classes
    ADD COLUMN IF NOT EXISTS egress_id VARCHAR(255) NULL DEFAULT NULL;

-- Tracks client-side Egress start failures for admin review
CREATE TABLE IF NOT EXISTS egress_failures (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    lesson_id    INT          NOT NULL,
    error_message TEXT,
    created_at   DATETIME     NOT NULL,
    FOREIGN KEY (lesson_id) REFERENCES live_classes(id) ON DELETE CASCADE
);

-- For MySQL versions that do not support IF NOT EXISTS in ALTER TABLE:
-- ALTER TABLE live_classes ADD COLUMN egress_id VARCHAR(255) NULL DEFAULT NULL;
