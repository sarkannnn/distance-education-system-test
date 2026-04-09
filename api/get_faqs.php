<?php

/**
 * Get FAQs API Endpoint
 * 
 * Bu API vasitəsilə Tez-tez Soruşulan Suallar (FAQs) əldə edilir.
 * Filtrlənmiş və ya hamısı qaytarıla bilərsən.
 */

header('Content-Type: application/json; charset=utf-8');
// Restrict CORS to the application's own origin only
$allowedOrigins = [
    'https://distant.ndu.edu.az',
    'https://tmis.ndu.edu.az',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Yalnız GET metodu dəstəklənilir.']);
    exit;
}

// Database connection
require_once dirname(__DIR__) . '/student/config/database.php';

try {
    $db = Database::getInstance();

    // Get query parameters
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

    // Build query
    $query = "SELECT id, question, answer, category, order_index FROM faqs WHERE is_active = 1";
    $params = [];

    // Filter by category if provided
    if ($category) {
        $query .= " AND category = ?";
        $params[] = $category;
    }

    // Filter by search if provided
    if ($search) {
        $query .= " AND (question LIKE ? OR answer LIKE ? OR category LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Order by category and order_index
    $query .= " ORDER BY category ASC, order_index ASC";

    // Apply limit if provided
    if ($limit && $limit > 0) {
        $query .= " LIMIT ?";
        $params[] = $limit;
    }

    // Execute query
    $faqs = $db->fetchAll($query, $params);

    // Get unique categories
    $categories_query = "SELECT DISTINCT category FROM faqs WHERE is_active = 1 ORDER BY category ASC";
    $categories = $db->fetchAll($categories_query);
    $categoryList = array_map(fn($c) => $c['category'], $categories);

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $faqs,
        'categories' => $categoryList,
        'count' => count($faqs)
    ]);
} catch (Exception $e) {
    error_log('get_faqs error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server xətası'
    ]);
}
