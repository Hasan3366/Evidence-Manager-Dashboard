<?php
/**
 * auth/process_login.php
 * Handles login form submission securely.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// This endpoint must only be used by POST requests.
if (!isPostRequest()) {
    setFlash('error', 'Invalid request. Please sign in using the login form.');
    redirect('/auth/login.php');
}

// If already logged in, go straight to dashboard.
if (isLoggedIn()) {
    redirect('/dashboard/index.php');
}

// Collect and sanitize input.
$submittedToken = trim((string) ($_POST['csrf_token'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$password = trim((string) ($_POST['password'] ?? ''));

// Validate CSRF token before any database logic.
if (!verifyCsrfToken($submittedToken)) {
    setFlash('error', 'Your session expired. Please try again.');
    redirect('/auth/login.php');
}

// Basic input validation.
if ($username === '' || $password === '') {
    setFlash('error', 'Invalid username or password.');
    redirect('/auth/login.php');
}

try {
    $pdo = getDB();

    // Only allow active users.
    $stmt = $pdo->prepare(
        'SELECT id, username, password_hash, full_name, role
         FROM users
         WHERE username = :username
           AND active = 1
         LIMIT 1'
    );
    $stmt->execute([':username' => $username]);

    $user = $stmt->fetch();

    // Generic error message avoids revealing which credential was wrong.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        setFlash('error', 'Invalid username or password.');
        redirect('/auth/login.php');
    }

    // Prevent session fixation after successful authentication.
    session_regenerate_id(true);

    // Store authenticated user data in the session.
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['full_name'] = (string) $user['full_name'];
    $_SESSION['role'] = (string) $user['role'];

    // Keep compatibility with existing auth helper conventions.
    $_SESSION['user_role'] = (string) $user['role'];

    // Rotate CSRF token after authentication.
    unset($_SESSION['csrf_token']);

    redirect('/dashboard/index.php');
} catch (Throwable $e) {
    // Never expose internal errors to end users.
    error_log('[Evidence Manager] Login failed: ' . $e->getMessage());
    setFlash('error', 'Unable to sign in right now. Please try again.');
    redirect('/auth/login.php');
}
