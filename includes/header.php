<?php
/**
 * includes/header.php
 * Reusable page header — outputs the full HTML <head>, security headers,
 * the navigation bar, and any queued flash messages.
 *
 * Variables you can set BEFORE including this file:
 *   $pageTitle  (string)  — inserted into the <title> tag
 *   $bodyClass  (string)  — added to the <body> class attribute (e.g. 'auth-page')
 *
 * This file bootstraps the entire include chain:
 *   header.php → constants.php → db.php → functions.php → auth.php
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// ── Security headers ──────────────────────────────────────────────────────────
// These instruct the browser to enforce stricter security policies, protecting
// against common attacks. Each header has a specific purpose:

// Prevent browsers from sniffing content type — stop them guessing that a text
// file is executable. Protects against MIME type confusion attacks.
header('X-Content-Type-Options: nosniff');

// Deny embedding this page in <iframe> on other sites — stops clickjacking attacks
// where an attacker tricks users into clicking hidden buttons.
// Use 'SAMEORIGIN' if the app needs to iframe itself internally.
header('X-Frame-Options: DENY');

// Control how much referrer information is sent when users navigate away from
// this site. 'strict-origin-when-cross-origin' sends only the origin to other
// sites, but full URL for same-origin navigation — a good privacy/usability balance.
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy — restrict where resources (scripts, styles, etc.) can
// come from. This helps prevent XSS attacks and data exfiltration.
// - self: Allow scripts/styles from this domain only
// - unsafe-inline: Allow inline <style> tags (set to false in future if possible)
// - fonts.googleapis.com: Google Fonts (also needs font-src exception)
// - In production, strongly consider removing 'unsafe-inline' and using nonces.
header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self'; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; "
    . "img-src 'self' data:; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'none';"
);
// ───────────────────────────────────────────────────────────────────────────────

// Build the page <title>
$_pageTitle = isset($pageTitle)
    ? e($pageTitle) . ' — ' . APP_NAME
    : APP_NAME;

// Body class — defaults to empty; auth pages pass 'auth-page'
$_bodyClass = isset($bodyClass) ? e($bodyClass) : '';

// Collect any queued flash messages before the HTML is sent
$_flashMessages = getFlash();

// Active navigation detection — highlights the current section
$_currentUri = $_SERVER['REQUEST_URI'] ?? '';
function _navActive(string $segment): string {
    global $_currentUri;
    return str_contains($_currentUri, $segment) ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= APP_NAME ?> — secure evidence logging for first responders">
    <title><?= $_pageTitle ?></title>

    <!-- Preconnect to Google Fonts for faster loading -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Main stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="<?= $_bodyClass ?>">

<?php if (isLoggedIn()):
    $user = currentUser();
?>
<!-- ═══════════════════════════════════════════════════════════
     NAVIGATION BAR
     ═══════════════════════════════════════════════════════════ -->
<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="nav-container">

        <!-- Brand / Logo -->
        <a href="<?= BASE_URL ?>/dashboard/index.php" class="nav-brand" aria-label="<?= APP_NAME ?> home">
            <span class="nav-logo" aria-hidden="true">EM</span>
            <span class="nav-brand-text"><?= APP_NAME ?></span>
        </a>

        <!-- Mobile hamburger button -->
        <button class="nav-toggle" id="navToggle"
                aria-controls="navLinks"
                aria-expanded="false"
                aria-label="Toggle navigation menu">
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
        </button>

        <!-- Navigation links -->
        <ul class="nav-links" id="navLinks" role="menubar">
            <li role="none">
                <a href="<?= BASE_URL ?>/dashboard/index.php"
                   class="nav-link<?= _navActive('/dashboard') ?>"
                   role="menuitem">
                    Dashboard
                </a>
            </li>
            <li role="none">
                <a href="<?= BASE_URL ?>/evidence/submit.php"
                   class="nav-link<?= _navActive('/evidence/submit') ?>"
                   role="menuitem">
                    Submit Evidence
                </a>
            </li>
            <?php if (hasRole(ROLE_ADMIN)): ?>
            <li role="none">
                <a href="<?= BASE_URL ?>/users/index.php"
                   class="nav-link<?= _navActive('/users') ?>"
                   role="menuitem">
                    Users
                </a>
            </li>
            <li role="none">
                <a href="<?= BASE_URL ?>/audit/index.php"
                   class="nav-link<?= _navActive('/audit') ?>"
                   role="menuitem">
                    Audit Log
                </a>
            </li>
            <?php endif; ?>
            <li role="none">
                <a href="<?= BASE_URL ?>/help/index.php"
                   class="nav-link<?= _navActive('/help') ?>"
                   role="menuitem">
                    Help
                </a>
            </li>
        </ul>

        <!-- User info + logout (desktop) -->
        <div class="nav-user">
            <div class="nav-user-info">
                <span class="nav-user-name"><?= e($user['full_name']) ?></span>
                <span class="nav-user-badge role-<?= e($user['role']) ?>">
                    <?= ucfirst(e($user['role'])) ?>
                </span>
            </div>
            <a href="<?= BASE_URL ?>/auth/logout.php"
               class="btn btn-sm btn-outline"
               onclick="return confirm('Are you sure you want to log out?')">
                Logout
            </a>
        </div>

    </div><!-- /.nav-container -->
</nav>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════
     MAIN CONTENT WRAPPER
     Pages add their own inner containers after this point.
     footer.php closes the </main> tag.
     ═══════════════════════════════════════════════════════════ -->
<main id="main-content" class="main-content" role="main">

    <?php if (!empty($_flashMessages)): ?>
    <!-- Flash message notifications -->
    <div class="flash-wrapper" aria-live="polite" aria-atomic="true">
        <?php foreach ($_flashMessages as $type => $message): ?>
            <div class="alert alert-<?= e($type) ?>" role="alert">
                <span><?= e($message) ?></span>
                <button class="alert-close" type="button" aria-label="Dismiss notification">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
