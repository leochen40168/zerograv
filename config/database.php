<?php
/**
 * ZeroGrav - 資料庫連線設定
 * 讀取 .env 檔案取得連線參數，回傳 PDO 實例
 */

declare(strict_types=1);

// ── 讀取 .env ────────────────────────────────────────────────
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException(".env 檔案不存在：{$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // 跳過註解行
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // 移除引號
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// ── 載入 .env（從專案根目錄往上找）───────────────────────────
$envFile = dirname(__DIR__) . '/.env';
loadEnv($envFile);

// ── 環境變數取得輔助 ─────────────────────────────────────────
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── PDO 單例 ─────────────────────────────────────────────────
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host    = env('DB_HOST', 'localhost');
    $port    = env('DB_PORT', '3306');
    $dbname  = env('DB_NAME', 'cycleflo_zerograv');
    $user    = env('DB_USER', 'cycleflo_zerograv');
    $pass    = env('DB_PASS', '');
    $charset = env('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci,
                                         time_zone='+08:00'",
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // 正式環境不顯示 DB 錯誤細節
        if (env('APP_DEBUG', 'false') === 'true') {
            throw $e;
        }
        throw new RuntimeException('資料庫連線失敗，請稍後再試。');
    }

    return $pdo;
}
