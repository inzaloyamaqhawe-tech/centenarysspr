<?php
/**
 * Centenary Networks — Self-Service Password Reset (SSPR)
 * api-request-reset.php
 *
 * POST endpoint. Body: { "email": "someone@client.com" }
 *
 * Always responds with the same generic success message regardless of
 * whether the email exists, to prevent user-enumeration attacks. Only when
 * the email genuinely exists does it generate a token and send mail.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// ---- Site configuration ----------------------------------------------------
// Update SITE_URL to your live Xneelo domain before going live.
const SITE_URL     = 'https://yourdomain.com';
const MAIL_FROM     = 'no-reply@yourdomain.com';
const MAIL_FROM_NAME = 'Centenary Networks';

header('Content-Type: application/json; charset=utf-8');

// Generic response used for every non-fatal outcome (found or not found).
$GENERIC_SUCCESS = [
    'status'  => 'success',
    'message' => 'If that email exists, a reset link has been sent.',
];

function respond(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

// ---- Method guard -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

// ---- Read + decode body (supports JSON body or classic form POST) ---------
$rawBody = file_get_contents('php://input');
$decoded = json_decode((string) $rawBody, true);
if (is_array($decoded) && array_key_exists('email', $decoded)) {
    $emailInput = $decoded['email'];
} else {
    $emailInput = $_POST['email'] ?? '';
}

// ---- Sanitize + validate email ----------------------------------------------
$email = filter_var(trim((string) $emailInput), FILTER_SANITIZE_EMAIL);

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Still generic — do not reveal that validation failed vs. "not found".
    respond($GENERIC_SUCCESS);
}

// ---- Look up the admin across ALL clients -----------------------------------
$stmt = $pdo->prepare('SELECT id FROM client_admins WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$admin = $stmt->fetch();

if ($admin === false) {
    // No such account — do not disclose this to the caller.
    respond($GENERIC_SUCCESS);
}

// ---- Generate a cryptographically secure token ------------------------------
$token      = bin2hex(random_bytes(32));
$tokenExpiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));

$update = $pdo->prepare(
    'UPDATE client_admins
     SET reset_token = :token, token_expiry = :expiry
     WHERE id = :id'
);
$update->execute([
    'token'  => $token,
    'expiry' => $tokenExpiry,
    'id'     => $admin['id'],
]);

// ---- Send the reset email ----------------------------------------------------
$resetLink = SITE_URL . '/reset-password.html?token=' . urlencode($token);

$subject = 'Centenary Networks — Password Reset Request';

$htmlBody = '<!DOCTYPE html><html><body style="font-family: Segoe UI, Arial, sans-serif; color:#1d1d1f; background:#f2f0ec; padding:24px;">'
    . '<div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:18px;padding:32px;border:1px solid #e2dfd9;">'
    . '<h2 style="color:#1d1d1f;margin-top:0;">Password Reset Request</h2>'
    . '<p>We received a request to reset the password for your Centenary Networks client administrator account.</p>'
    . '<p style="margin:28px 0;text-align:center;">'
    . '<a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" '
    . 'style="background:#e0475f;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:999px;font-weight:600;display:inline-block;">Reset My Password</a>'
    . '</p>'
    . '<p>This link expires in 30 minutes. If you did not request this, you can safely ignore this email — your password will not be changed.</p>'
    . '<p style="color:#918d86;font-size:12px;margin-top:32px;">Centenary Networks — Client Portal</p>'
    . '</div></body></html>';

$headers  = 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
$headers .= 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>' . "\r\n";
$headers .= 'X-Mailer: PHP/' . phpversion();

// mail() failures must not leak internal state to the caller — log instead.
$mailSent = @mail($email, $subject, $htmlBody, $headers);
if (!$mailSent) {
    error_log('SSPR api-request-reset.php: mail() failed for admin id ' . $admin['id']);
}

respond($GENERIC_SUCCESS);
