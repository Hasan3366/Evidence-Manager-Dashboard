<?php
/**
 * config/db.php
 * PDO database connection — returns a single shared PDO instance.
 *
 * SETUP: Edit the four DB_* constants below to match your environment.
 */

// ── Database credentials ────────────────────────────────────────────────────
define('DB_HOST',    'localhost');          // Database server hostname
define('DB_PORT',    '3306');              // MySQL/MariaDB default port
define('DB_NAME',    'evidence_manager');  // Database name you created
define('DB_USER',    'root');              // CHANGE: your database username
define('DB_PASS',    '');                  // CHANGE: your database password
define('DB_CHARSET', 'utf8mb4');           // Full Unicode support
// ────────────────────────────────────────────────────────────────────────────

/**
 * Returns a shared PDO instance (singleton pattern).
 * The connection is created once per request and reused.
 *
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            // Throw exceptions on SQL errors (do not use silent failures)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return rows as associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real prepared statements (prevents emulated statement attacks)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the real error server-side; never expose DB details to users
            error_log('[Evidence Manager] DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('A database error occurred. Please contact the system administrator.');
        }
    }

    return $pdo;
}
