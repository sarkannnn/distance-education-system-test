-- Migration: Create Chatbot Logs Table
-- Description: Stores user interactions with the AI Chatbot for auditing and improvement.

CREATE TABLE IF NOT EXISTS chatbot_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    user_role VARCHAR(50) DEFAULT 'guest',
    session_id VARCHAR(255) DEFAULT NULL,
    query TEXT NOT NULL,
    response TEXT NOT NULL,
    source VARCHAR(50) NOT NULL, -- 'local_faq', 'gemini', 'openai', 'fallback'
    model VARCHAR(255) DEFAULT NULL, -- specific model name if AI
    ip_address VARCHAR(45) DEFAULT NULL,
    page_url TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (session_id),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
