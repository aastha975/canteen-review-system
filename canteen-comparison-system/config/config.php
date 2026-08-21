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
        'user'     => 'postgres',
        'password' => 'drawing12345',
    ],
    'app' => [
        'session_name'    => 'canteen_session',
        'session_timeout' => 1800, // 30 minutes, in seconds
    ],
];
