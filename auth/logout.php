<?php
/**
 * auth/logout.php
 * Logs out the current user and destroys the session safely.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Clear all session data.
$_SESSION = [];

// Remove session cookie from browser if cookies are in use.
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

// Destroy the session storage.
session_destroy();

// Start fresh session for the flash message.
session_start();
setFlash('success', 'You have been logged out successfully.');

redirect('/auth/login.php');
