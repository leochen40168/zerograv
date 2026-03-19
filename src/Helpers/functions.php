<?php
/**
 * ZeroGrav - 通用輔助函式
 */

declare(strict_types=1);

// ── 輸出安全 ─────────────────────────────────────────────────

/** HTML 特殊字元跳脫（防 XSS） */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── CSRF ────────────────────────────────────────────────────

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('無效的請求（CSRF 驗證失敗）');
    }
}

// ── 導向 ─────────────────────────────────────────────────────

function redirect(string $url, int $code = 302): never
{
    header("Location: {$url}", true, $code);
    exit;
}

// ── Flash 訊息 ───────────────────────────────────────────────

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ── 分頁 ─────────────────────────────────────────────────────

function paginate(int $total, int $perPage, int $currentPage): array
{
    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
        'prev_page'    => $currentPage - 1,
        'next_page'    => $currentPage + 1,
    ];
}

// ── 格式化 ───────────────────────────────────────────────────

function formatPrice(int|float|null $price): string
{
    if ($price === null) return '面議';
    return 'NT$ ' . number_format((float)$price, 0);
}

function formatDate(string|null $datetime, string $format = 'Y/m/d'): string
{
    if (empty($datetime)) return '-';
    return date($format, strtotime($datetime));
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    return match (true) {
        $diff < 60     => '剛剛',
        $diff < 3600   => (int)($diff / 60) . ' 分鐘前',
        $diff < 86400  => (int)($diff / 3600) . ' 小時前',
        $diff < 604800 => (int)($diff / 86400) . ' 天前',
        default        => formatDate($datetime),
    };
}

// ── 成色標籤 ─────────────────────────────────────────────────

function conditionLabel(string $condition): string
{
    return match ($condition) {
        'like_new'  => '九成新',
        'good'      => '良好',
        'fair'      => '尚可',
        'for_parts' => '零件用',
        default     => $condition,
    };
}

function conditionColor(string $condition): string
{
    return match ($condition) {
        'like_new'  => 'green',
        'good'      => 'blue',
        'fair'      => 'yellow',
        'for_parts' => 'gray',
        default     => 'gray',
    };
}

// ── 狀態標籤 ─────────────────────────────────────────────────

function statusLabel(string $status): string
{
    return match ($status) {
        'draft'    => '草稿',
        'pending'  => '待審核',
        'active'   => '上架中',
        'inactive' => '已下架',
        'rejected' => '已拒絕',
        default    => $status,
    };
}

// ── 其他 ─────────────────────────────────────────────────────

function currentUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('warning', '請先登入');
        redirect('/login.php');
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        include __DIR__ . '/../../templates/errors/403.php';
        exit;
    }
}

/** 取得使用者上傳圖片的公開路徑 */
function uploadUrl(string $filename, int $listingId): string
{
    return '/uploads/listings/' . $listingId . '/' . $filename;
}
