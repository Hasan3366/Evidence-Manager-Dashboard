<?php
/**
 * config/db.php
 * PDO database connection — returns a single shared PDO instance.
 *
 * SETUP: Edit the four DB_* constants below to match your environment.
 */

// Development debugging: show full PHP errors locally.
// In production, keep display_errors disabled for safety.
$appEnv = getenv('APP_ENV') ?: 'development';
if ($appEnv !== 'production') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

// ── Database credentials ────────────────────────────────────────────────────
define('DB_HOST',    '127.0.0.1');          // Use IP so PHP connects over TCP, not socket
define('DB_PORT',    '3306');              // MySQL/MariaDB default port
define('DB_NAME',    'evidence_manager');  // Database name
define('DB_USER',    'em_user');           // App database user
define('DB_PASS',    'em_pass_dev');       // App database password (change in production)
define('DB_CHARSET', 'utf8mb4');           // Full Unicode support
// ────────────────────────────────────────────────────────────────────────────

// Create one shared PDO instance immediately for scripts that expect $pdo.
$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Development: show exact database error for fast debugging.
    // Production: show generic error, log full details server-side.
    if ($appEnv !== 'production') {
        die('Database connection failed: ' . $e->getMessage());
    }

    error_log('[Evidence Manager] DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('A database error occurred. Please contact the system administrator.');
}

/**
 * Returns a shared PDO instance (singleton pattern).
 * The connection is created once per request and reused.
 *
 * @return PDO
 */
function getDB(): PDO
{
    global $pdo;
    return $pdo;
}
