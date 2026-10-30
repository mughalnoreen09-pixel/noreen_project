<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
if (current_user()) {
    header('Location: dashboard.php');
    exit;
}
$queryString = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: login.php' . $queryString);
exit;
