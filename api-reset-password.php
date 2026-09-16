<?php
/**
 * Centenary Networks — Self-Service Password Reset (SSPR)
 * api-reset-password.php
 *
 * POST endpoint. Body: { "token": "...", "new_password": "..." }
 *
 * Validates the token against client_admins, enforces a minimum password
 * length, hashes the new password with bcrypt, and invalidates the token
 * immediately so it cannot be replayed.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

// ---- Method guard -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

// ---- Read + decode body ---------------------------------------------------
$rawBody = file_get_contents('php://input');
$decoded = json_decode((string) $rawBody, true);
if (is_array($decoded)) {
    $tokenInput    = $decoded['token'] ?? '';
    $newPassword   = $decoded['new_password'] ?? '';
} else {
    $tokenInput    = $_POST['token'] ?? '';
    $newPassword   = $_POST['new_password'] ?? '';
}

$token       = trim((string) $tokenInput);
$newPassword = (string) $newPassword;

// ---- Validate token presence + format (64 hex chars from bin2hex(32 bytes)) --
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    respond(['status' => 'error', 'message' => 'Invalid or expired reset link. Please request a new one.'], 400);
}

// ---- Validate password strength on the backend -----------------------------
if (strlen($newPassword) < 8) {
    respond(['status' => 'error', 'message' => 'Password must be at least 8 characters long.'], 400);
}

// ---- Look up an active, non-expired token -----------------------------------
$stmt = $pdo->prepare(
    'SELECT id FROM client_admins
     WHERE reset_token = :token AND token_expiry > NOW()
     LIMIT 1'
);
$stmt->execute(['token' => $token]);
$admin = $stmt->fetch();

if ($admin === false) {
    respond(['status' => 'error', 'message' => 'Invalid or expired reset link. Please request a new one.'], 400);
}

// ---- Hash the new password and invalidate the token --------------------------
$passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

$update = $pdo->prepare(
    'UPDATE client_admins
     SET password_hash = :hash, reset_token = NULL, token_expiry = NULL
     WHERE id = :id'
);
$update->execute([
    'hash' => $passwordHash,
    'id'   => $admin['id'],
]);

respond([
    'status'  => 'success',
    'message' => 'Password has been successfully updated. You can now log in.',
]);
