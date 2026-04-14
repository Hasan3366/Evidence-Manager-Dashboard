<?php
/**
 * index.php — Application entry point
 *
 * This file lives at the web root of the application.
 * Its only job is to redirect visitors to the right place:
 *   - Authenticated users  →  dashboard/index.php
 *   - Guests               →  auth/login.php
 *
 * All actual page logic lives in the subdirectory files.
 */

// Bootstrap: load constants, start session, load auth helpers
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    // User already has a valid session — send them to the dashboard
    redirect('/dashboard/index.php');
} else {
    // No session — send them to the login page
    redirect('/auth/login.php');
}
