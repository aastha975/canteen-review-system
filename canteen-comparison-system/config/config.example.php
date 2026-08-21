<?php
// ============================================================
// Copy this file to config.php and fill in real values.
// config.php is gitignored — never commit real credentials.
// ============================================================

return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 5432,
        'dbname'   => 'canteen_comparison',
        'user'     => 'your_db_user',
        'password' => 'your_db_password',
    ],
    'app' => [
        'session_name'    => 'canteen_session',
        'session_timeout' => 1800, // 30 minutes, in seconds
    ],
];
