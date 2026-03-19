<?php
/**
 * API：廣告點擊計數
 * POST /api/banner-click.php?id={banner_id}
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        getPDO()->prepare("UPDATE banners SET click_count = click_count + 1 WHERE id = ?")
               ->execute([$id]);
    } catch (Exception) {}
}

http_response_code(204);
