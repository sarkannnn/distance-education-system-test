<?php
/**
 * LoggerService - Handles logging of chatbot interactions to the database.
 */
require_once dirname(__DIR__) . '/../student/config/database.php';

class LoggerService
{
    /**
     * Log a single interaction
     * 
     * @param string $query Use message
     * @param string $response Bot response
     * @param string $source Source (gemini, openai, local_faq, etc)
     * @param string|null $model AI model name
     * @return bool
     */
    public static function log($query, $response, $source, $model = null)
    {
        try {
            $db = Database::getInstance();
            
            // Determine User Identity
            $userId = null;
            $userRole = 'guest';
            $sessionId = session_id();

            // Check Student Session
            if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['user_role'] === 'student') {
                $userId = $_SESSION['user_id'];
                $userRole = 'student';
            } 
            // Check Teacher Session
            else if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['user_role'] === 'instructor') {
                $userId = $_SESSION['user_id'];
                $userRole = 'instructor';
            }
            
            $data = [
                'user_id' => $userId,
                'user_role' => $userRole,
                'session_id' => $sessionId ?: 'no-session',
                'query' => $query,
                'response' => $response,
                'source' => $source,
                'model' => $model,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'page_url' => $_SERVER['HTTP_REFERER'] ?? null
            ];

            return $db->insert('chatbot_logs', $data);
        } catch (Exception $e) {
            error_log("Chatbot Logging Error: " . $e->getMessage());
            return false;
        }
    }
}
