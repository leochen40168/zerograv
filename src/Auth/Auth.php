<?php
/**
 * ZeroGrav - 認證模組
 * 處理登入、登出、註冊、Session 管理
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__) . '/Helpers/functions.php';

class Auth
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getPDO();
        $this->startSession();
    }

    // ── Session 初始化 ───────────────────────────────────────

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionName = env('SESSION_NAME', 'zerograv_sess');
            $lifetime    = (int)env('SESSION_LIFETIME', 7200);

            session_name($sessionName);
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'secure'   => (env('APP_ENV') === 'production'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    // ── 註冊 ─────────────────────────────────────────────────

    /**
     * @return array{success: bool, errors: array<string>}
     */
    public function register(array $data): array
    {
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // 檢查 Email 是否重複
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'errors' => ['此 Email 已被註冊']];
        }

        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, phone, line_id, company)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($data['name']),
            strtolower(trim($data['email'])),
            $hash,
            trim($data['phone'] ?? ''),
            trim($data['line_id'] ?? ''),
            trim($data['company'] ?? ''),
        ]);

        return ['success' => true, 'errors' => []];
    }

    // ── 登入 ─────────────────────────────────────────────────

    /**
     * @return array{success: bool, message: string}
     */
    public function login(string $email, string $password): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password, role, is_active FROM users WHERE email = ?'
        );
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Email 或密碼錯誤'];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'message' => '帳號已停用，請聯絡管理員'];
        }

        // 防 Session fixation
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];

        // 更新最後登入時間
        $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
                 ->execute([$user['id']]);

        return ['success' => true, 'message' => '登入成功'];
    }

    // ── 登出 ─────────────────────────────────────────────────

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    // ── 取得目前登入使用者 ───────────────────────────────────

    public function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT id, name, email, phone, line_id, company, role FROM users WHERE id = ?'
        );
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    // ── 驗證規則 ─────────────────────────────────────────────

    private function validateRegistration(array $data): array
    {
        $errors = [];

        if (empty(trim($data['name'] ?? ''))) {
            $errors[] = '請填寫姓名';
        } elseif (mb_strlen(trim($data['name'])) > 100) {
            $errors[] = '姓名不得超過 100 字';
        }

        if (empty(trim($data['email'] ?? ''))) {
            $errors[] = '請填寫 Email';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email 格式不正確';
        }

        $pw = $data['password'] ?? '';
        if (strlen($pw) < 8) {
            $errors[] = '密碼至少需要 8 個字元';
        } elseif (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
            $errors[] = '密碼需包含英文字母與數字';
        }

        if (($data['password'] ?? '') !== ($data['password_confirm'] ?? '')) {
            $errors[] = '兩次密碼輸入不一致';
        }

        return $errors;
    }
}
