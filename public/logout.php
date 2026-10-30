<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}
if (!verify_csrf($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    exit('Invalid security token.');
}
logout_user();
header('Location: index.php');
exit;
