<?php
/**
 * Centenary Networks — Self-Service Password Reset (SSPR)
 * api-reset-password.php
 *
 * POST endpoint. Body: { "token": "...", "email": "...", "new_password": "..." }
 *
 * Validates the token against client_admins, enforces a minimum password
 * length, hashes the new password with bcrypt, and invalidates the token
 * immediately so it cannot be replayed.
 *
 * The caller must also supply the account's email address. This is checked
 * against the row the token belongs to (not used to look the row up) — it
 * stops someone who merely got hold of the reset link (a forwarded email, a
 * shared screen, browser history on a shared machine) from resetting the
 * password without also knowing which account it belongs to. It is not a
 * lookup key, so it cannot be used to enumerate accounts: an email/token
 * mismatch returns the same generic "invalid or expired" error as a bad
 * token.
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
    $tokenInput  = $decoded['token'] ?? '';
    $emailInput  = $decoded['email'] ?? '';
    $newPassword = $decoded['new_password'] ?? '';
} else {
    $tokenInput  = $_POST['token'] ?? '';
    $emailInput  = $_POST['email'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
}

$token       = trim((string) $tokenInput);
$email       = filter_var(trim((string) $emailInput), FILTER_SANITIZE_EMAIL);
$newPassword = (string) $newPassword;

$genericTokenError = ['status' => 'error', 'message' => 'Invalid or expired reset link. Please request a new one.'];

// ---- Validate token presence + format (64 hex chars from bin2hex(32 bytes)) --
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    respond($genericTokenError, 400);
}

// ---- Validate email presence/format -----------------------------------------
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['status' => 'error', 'message' => 'Please enter the email address for this account.'], 400);
}

// ---- Validate password strength on the backend -----------------------------
if (strlen($newPassword) < 8) {
    respond(['status' => 'error', 'message' => 'Password must be at least 8 characters long.'], 400);
}

// ---- Look up an active, non-expired token -----------------------------------
$stmt = $pdo->prepare(
    'SELECT id, email FROM client_admins
     WHERE reset_token = :token AND token_expiry > NOW()
     LIMIT 1'
);
$stmt->execute(['token' => $token]);
$admin = $stmt->fetch();

if ($admin === false) {
    respond($genericTokenError, 400);
}

// ---- Confirm the supplied email matches the token's account ------------------
// Same generic error as a bad token — never reveal whether the token itself
// was valid, so this check can't be used to enumerate the correct email.
if (!hash_equals(strtolower($admin['email']), strtolower($email))) {
    respond($genericTokenError, 400);
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
