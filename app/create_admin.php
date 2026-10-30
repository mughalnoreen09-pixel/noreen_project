<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}
require_once __DIR__ . '/db.php';

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php app/create_admin.php \"Full Name\" \"admin@example.com\" \"Long-Unique-Password\"\n");
    exit(2);
}
[$script, $name, $email, $password] = $argv;
$name = trim($name);
$email = strtolower(trim($email));
if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Invalid input. Name/email required; password must be at least 12 characters.\n");
    exit(2);
}
try {
    $stmt = db()->prepare("
        INSERT INTO users (name, email, password_hash, role, is_active, account_status)
        VALUES (:name, :email, :hash, 'admin', 1, 'approved')
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            password_hash = VALUES(password_hash),
            role = 'admin',
            is_active = 1,
            account_status = 'approved'
    ");
    $stmt->execute(['name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Admin account saved successfully for {$email}\n");
} catch (PDOException $e) {
    fwrite(STDERR, "Could not save admin account: " . $e->getMessage() . "\n");
    exit(1);
}
