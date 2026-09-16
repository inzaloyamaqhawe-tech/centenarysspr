<?php
/**
 * Centenary Networks — Self-Service Password Reset (SSPR)
 * generate-hash.php
 *
 * ONE-TIME TESTING UTILITY — not part of the production application flow.
 * Visit this file in a browser with ?password=YourTestPassword123 to get a
 * bcrypt hash you can paste into a client_admins.password_hash column via
 * phpMyAdmin, so you have a seeded account to test the reset loop against.
 *
 * DELETE THIS FILE from the server once you have created your test admin
 * row — it must never be left reachable in production.
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$password = $_GET['password'] ?? '';

if ($password === '') {
    echo "Usage: generate-hash.php?password=YourTestPassword123\n";
    exit;
}

echo password_hash((string) $password, PASSWORD_BCRYPT) . "\n";
