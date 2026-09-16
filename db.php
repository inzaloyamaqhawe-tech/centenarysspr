<?php
/**
 * Centenary Networks — Self-Service Password Reset (SSPR)
 * db.php
 *
 * Secure PDO/MySQL connection. Include this file first from every
 * api-*.php endpoint: `require_once __DIR__ . '/db.php';` gives you a
 * ready-to-use $pdo handle.
 *
 * IMPORTANT: fill in the four values below with the database details shown
 * in your Xneelo Control Panel -> Manage MySQL screen after you create the
 * database. Do not commit real production credentials to a public git repo.
 */

declare(strict_types=1);

// ---- Database connection settings -----------------------------------------
$DB_HOST = 'localhost';           // Xneelo MySQL host (usually "localhost")
$DB_NAME = 'your_database_name';  // e.g. abc123_sspr
$DB_USER = 'your_database_user';  // e.g. abc123_ssprusr
$DB_PASS = 'your_database_password';

// ---- Connect ----------------------------------------------------------------
$dsn = 'mysql:host=' . $DB_HOST . ';dbname=' . $DB_NAME . ';charset=utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // Never leak connection details or raw exception messages to the client.
    http_response_code(500);
    header('Content-Type: application/json');
    error_log('SSPR db.php connection failure: ' . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'A server error occurred. Please try again later.',
    ]);
    exit;
}
