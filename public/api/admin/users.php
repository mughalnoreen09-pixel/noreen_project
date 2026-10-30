<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';
$admin = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->query('SELECT id, name, email, username, role, is_active, created_at FROM users ORDER BY id DESC LIMIT 200');
    json_response(['users' => $stmt->fetchAll()]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($csrf)) {
        json_response(['error' => 'Invalid CSRF token'], 400);
    }
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        json_response(['error' => 'Expected a JSON request body'], 400);
    }
    $name = trim((string) ($payload['name'] ?? ''));
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $username = isset($payload['username']) ? trim((string) $payload['username']) : null;
    $password = (string) ($payload['password'] ?? '');
    $role = (string) ($payload['role'] ?? '');
    if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 254 || strlen($password) < 12 || strlen($password) > 4096
        || !in_array($role, ['admin', 'receptionist', 'stylist'], true)
        || ($username !== null && ($username === '' || strlen($username) > 80))) {
        json_response(['error' => 'Invalid fields. Password must be at least 12 characters.'], 422);
    }
    try {
        $stmt = db()->prepare("INSERT INTO users (name,email,username,password_hash,role,is_active,account_status) VALUES (:name,:email,:username,:hash,:role,1,'approved')");
        $stmt->execute([
            'name' => $name, 'email' => $email, 'username' => $username,
            'hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role,
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            json_response(['error' => 'Email or username already exists'], 409);
        }
        error_log('SalonDiva create user database error: ' . $e->getMessage());
        json_response(['error' => 'Server error'], 500);
    }
    json_response(['created' => true], 201);
}
header('Allow: GET, POST');
json_response(['error' => 'Method not allowed'], 405);
