<?php
/**
 * API：記錄聯絡點擊
 * POST /api/contact-log.php
 * Body (JSON): { listing_id: int, type: "phone"|"line"|"copy" }
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$listingId = (int)($body['listing_id'] ?? 0);
$type      = $body['type'] ?? '';

if ($listingId <= 0 || !in_array($type, ['phone', 'line', 'copy'])) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

try {
    $db = getPDO();
    $db->prepare(
        "INSERT INTO contact_logs (listing_id, contact_type, visitor_ip) VALUES (?, ?, ?)"
    )->execute([$listingId, $type, substr($ip, 0, 45)]);
    echo json_encode(['ok' => true]);
} catch (Exception) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
