<?php
/**
 * includes/auth.php
 * Authentication helpers — include this in every protected page.
 *
 * Requires: config/constants.php, includes/functions.php (for session start).
 * Both are loaded by header.php, so you don't normally need to require them
 * separately — just include header.php at the top of each page.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php'; // Also starts the session

// ── Session state checks ─────────────────────────────────────────────────────

/**
 * Return true if the current visitor has an active authenticated session.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

/**
 * Return the current user's session data as an associative array.
 * Returns null if the user is not logged in.
 *
 * Keys: id, username, full_name, role, badge_number
 *
 * @return array<string, mixed>|null
 */
function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'           => (int) $_SESSION['user_id'],
        'username'     => $_SESSION['username']      ?? '',
        'full_name'    => $_SESSION['full_name']     ?? '',
        'role'         => $_SESSION['user_role']     ?? '',
        'badge_number' => $_SESSION['badge_number']  ?? '',
    ];
}

/**
 * Return true if the current user has the specified role.
 *
 * Usage:  hasRole(ROLE_ADMIN)   or   hasRole(ROLE_OFFICER)
 *
 * @param  string $role  One of the ROLE_* constants
 * @return bool
 */
function hasRole(string $role): bool
{
    return isLoggedIn() && ($_SESSION['user_role'] === $role);
}

// ── Access guards ────────────────────────────────────────────────────────────

/**
 * Redirect unauthenticated visitors to the login page.
 * Place this call at the very top of every protected page.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        // Store where the user was trying to go so we can redirect back after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Redirect non-admin users to the dashboard with an error message.
 * Place this at the top of every admin-only page, after requireLogin().
 */
function requireAdmin(): void
{
    requireLogin();

    if (!hasRole(ROLE_ADMIN)) {
        setFlash('error', 'Access denied. Administrator privileges are required.');
        header('Location: ' . BASE_URL . '/dashboard/index.php');
        exit;
    }
}

// ── Session management ───────────────────────────────────────────────────────

/**
 * Populate the session after a successful login.
 * Regenerates the session ID to prevent session fixation attacks.
 *
 * @param array<string, mixed> $user  Row from the users table
 */
function loginUser(array $user): void
{
    // Prevent session fixation: give the authenticated session a new ID
    session_regenerate_id(true);

    $_SESSION['user_id']      = $user['id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['full_name']    = $user['full_name'];
    $_SESSION['user_role']    = $user['role'];
    $_SESSION['badge_number'] = $user['badge_number'] ?? '';
}

/**
 * Destroy the current session and clear the session cookie.
 * Call this from auth/logout.php.
 */
function logoutUser(): void
{
    $_SESSION = [];

    // Remove the session cookie from the browser
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
