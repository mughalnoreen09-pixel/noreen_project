<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
$user = current_user();
if (!$user) {
    json_response(['error' => 'Unauthenticated'], 401);
}
json_response(['user' => $user]);
