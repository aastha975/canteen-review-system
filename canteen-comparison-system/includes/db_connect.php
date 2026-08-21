<?php
// ============================================================
// db_connect.php
// Opens one shared PDO connection for the whole app, using
// config/config.php (copy of config.example.php with real values).
// ============================================================

function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $configPath = __DIR__ . '/../config/config.php';

    if (!file_exists($configPath)) {
        throw new RuntimeException(
            'config/config.php not found. Copy config/config.example.php to ' .
            'config/config.php and fill in your database credentials.'
        );
    }

    $config = require $configPath;
    $db = $config['db'];

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        $db['host'],
        $db['port'],
        $db['dbname']
    );

    try {
        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // Don't leak connection details to the browser
        error_log('DB connection failed: ' . $e->getMessage());
        die('Database connection failed. Please try again later.');
    }

    return $pdo;
}
