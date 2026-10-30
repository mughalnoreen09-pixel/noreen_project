<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && isset($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $token);
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, username, role, is_active, account_status FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1 || $user['account_status'] !== 'approved') {
        logout_user();
        return null;
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo '403 Forbidden: insufficient permissions.';
        exit;
    }
    return $user;
}

function login_user(string $identity, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT id, password_hash, is_active, account_status FROM users
         WHERE email = :email OR username = :username LIMIT 1'
    );
    $stmt->execute(['email' => $identity, 'username' => $identity]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['is_active'] !== 1 || $row['account_status'] !== 'approved'
        || !password_verify($password, $row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['authenticated_at'] = time();
    unset($_SESSION['login_failures'], $_SESSION['login_window_start']);
    return true;
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
