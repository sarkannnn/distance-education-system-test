<?php
/**
 * NDU Chatbot — Optimized Fallback Architecture
 * 
 * This file coordinates the 4-layer fallback system:
 * 1. Local FAQ Match (High Accuracy, Zero Cost)
 * 2. Gemini API (Primary AI)
 * 3. ChatGPT API (Secondary AI)
 * 4. Hardcoded Knowledge Base (Ultimate Fallback)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Yalnız POST metodu dəstəklənilir.']);
    exit;
}

// Load configurations and services
require_once __DIR__ . '/services/fallbackManager.php';
require_once dirname(__DIR__) . '/student/config/database.php'; // Loads .env

// Get API Keys from environment
$GEMINI_API_KEY = getenv('GEMINI_API_KEY');
$OPENAI_API_KEY = getenv('OPENAI_API_KEY');

// --- System Instruction ---
$systemInstruction = <<<PROMPT
Sən Naxçıvan Dövlət Universitetinin (NDU) Distant Təhsil Mərkəzinin rəsmi dəstək köməkçisisən. Adın "NDU Dəstək Asistenti"dir.
Platformada canlı dərslər, video arxiv və elektron resurslar mövcuddur. Tələbə girişi: student/login.php, Müəllim girişi: teacher/login.php.
Həmişə Azərbaycan dilində, professional və dostcasına cavab ver. Markdown istifadə etmə, yalnız HTML (<b>, <br>) istifadə et.
PROMPT;

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');
$history = $input['history'] ?? [];

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['error' => 'Mesaj boşdur.']);
    exit;
}

try {
    // Initialize Fallback Manager
    $manager = new FallbackManager($GEMINI_API_KEY, $OPENAI_API_KEY, $systemInstruction);
    
    // Process the message
    $result = $manager->process($userMessage, $history);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'reply' => $result['reply'],
            'source' => $result['source'],
            'model' => $result['model'] ?? null
        ]);
    } else {
        throw new Exception("Cavab alına bilmədi.");
    }

} catch (Exception $e) {
    error_log("Chatbot Main Error: " . $e->getMessage());
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'Xidmətdə müvəqqəti problem yarandı. Zəhmət olmasa bir qədər sonra yenidən cəhd edin.',
        'detail' => $e->getMessage()
    ]);
}
