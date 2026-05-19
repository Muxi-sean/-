<?php
// Simple API router
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($uri) {
    case '/api/trade/search':
        require __DIR__ . '/search.php';
        break;
    case '/api/trade/dicts':
        require __DIR__ . '/dicts.php';
        break;
    case '/api/health':
        echo json_encode(['status' => 'ok']);
        break;
    default:
        http_response_code(404);
        echo json_encode(['code' => 404, 'msg' => 'Not Found']);
        break;
}
