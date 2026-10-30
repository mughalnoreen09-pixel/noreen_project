<?php
declare(strict_types=1);

return [
    'db_host' => getenv('SALONDIVA_DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('SALONDIVA_DB_PORT') ?: '3306',
    'db_name' => getenv('SALONDIVA_DB_NAME') ?: 'salondiva',
    'db_user' => getenv('SALONDIVA_DB_USER') ?: 'root',
    'db_pass' => getenv('SALONDIVA_DB_PASS') ?: '',

    'session_secure' => (getenv('SALONDIVA_SESSION_SECURE') ?: '0') === '1',
    'session_name' => 'salondiva_session',
    'login_max_attempts' => 8,
    'login_window_minutes' => 15,
];
