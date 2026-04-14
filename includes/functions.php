<?php
/**
 * includes/functions.php
 * Shared utility functions used across the entire application.
 *
 * Included automatically via includes/header.php.
 * Can also be included directly in lightweight scripts (e.g. index.php).
 */

// Start the session exactly once per request
if (session_status() === PHP_SESSION_NONE) {
    // ── Session cookie hardening ───────────────────────────────────────────────
    // Prevents session hijacking and CSRF attacks by controlling when cookies
    // are sent across origins and how they can be accessed.
    //
    // - lifetime=0:      Session cookie — cleared when browser closes (no persistent on-disk cookie)
    // - path='/':        Cookie available to entire application
    // - secure=false:    Allows HTTP during development; set to true in production (HTTPS only)
    // - httponly=true:   Prevents JavaScript from reading the session cookie (defends against XSS)
    // - samesite='Lax':  Sends cookie for top-level navigations (links) but not form submissions
    //                    from other sites. Balances security vs. usability (Strict blocks useful flows).
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,      // CHANGE TO: true (when using HTTPS in production)
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Output & encoding ────────────────────────────────────────────────────────

/**
 * HTML-encode a value for safe output in HTML.
 * ALWAYS use this function when echoing user-supplied data.
 *
 * @param  mixed  $value  Any value that can be cast to string
 * @return string         HTML-safe string
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Navigation ───────────────────────────────────────────────────────────────

/**
 * Redirect the browser to a URL and stop execution.
 *
 * Pass an absolute path (starting with /) — BASE_URL is prepended automatically.
 * Pass a full http:// or https:// URL to redirect externally.
 *
 * @param string $path  Path or full URL to redirect to
 */
function redirect(string $path): void
{
    // External URL: redirect as-is
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . BASE_URL . $path);
    }
    exit;
}

// ── Flash messages ───────────────────────────────────────────────────────────

/**
 * Store a one-time flash message in the session.
 * The message is displayed once on the next page load, then cleared.
 *
 * @param string $type    'success' | 'error' | 'warning' | 'info'
 * @param string $message Human-readable message text
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/**
 * Retrieve all pending flash messages and clear them from the session.
 * Returns an associative array keyed by type, or an empty array.
 *
 * @return array<string, string>
 */
function getFlash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ── CSRF protection ──────────────────────────────────────────────────────────

/**
 * Generate a CSRF token and store it in the session.
 * Call this once per form and embed the returned token in a hidden input.
 *
 * @return string  32-byte hex token
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify that a submitted CSRF token matches the one stored in the session.
 * Call this at the top of every POST handler before processing form data.
 *
 * Uses hash_equals() to prevent timing attacks.
 *
 * @param  string $submittedToken  The token from $_POST['csrf_token']
 * @return bool                    true if valid, false otherwise
 */
function verifyCsrfToken(string $submittedToken): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if ($sessionToken === '' || $submittedToken === '') {
        return false;
    }
    return hash_equals($sessionToken, $submittedToken);
}

// ── Date & file helpers ──────────────────────────────────────────────────────

/**
 * Format a database datetime string for human-readable display.
 *
 * @param string|null $datetime  Raw datetime string from DB (e.g. '2026-04-14 10:30:00')
 * @param string      $format    PHP date format string
 * @return string                Formatted string, or '—' if empty
 */
function formatDate(?string $datetime, string $format = 'd M Y, H:i'): string
{
    if (empty($datetime)) {
        return '—';
    }
    try {
        return (new DateTime($datetime))->format($format);
    } catch (Exception) {
        return '—';
    }
}

/**
 * Format a file size in bytes to a human-readable string (B / KB / MB).
 *
 * @param int $bytes  Size in bytes
 * @return string     e.g. '1.4 MB'
 */
function formatFileSize(int $bytes): string
{
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1_048_576)  return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1_048_576, 1) . ' MB';
}

// ── Audit logging ────────────────────────────────────────────────────────────

/**
 * Record an action in the audit_log table for chain of custody tracking.
 *
 * @param PDO        $pdo         Active PDO connection
 * @param int        $userId      ID of the user performing the action
 * @param string     $action      Short action label: 'created' | 'updated' | 'viewed' | 'file_uploaded' | etc.
 * @param int|null   $evidenceId  Evidence record ID (null for non-evidence actions)
 * @param string     $detail      Optional human-readable or JSON summary of what changed
 */
function logAudit(PDO $pdo, int $userId, string $action, ?int $evidenceId = null, string $detail = ''): void
{
    // Capture the client IP and User-Agent for chain of custody traceability.
    // REMOTE_ADDR is the network-level address and cannot be spoofed at TCP level.
    // HTTP_USER_AGENT is client-supplied and can be faked, but is still useful context.
    $ip        = $_SERVER['REMOTE_ADDR']       ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT']   ?? null;
    // Truncate user agent to fit the 255-char column limit
    if ($userAgent !== null) {
        $userAgent = mb_substr($userAgent, 0, 255);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO audit_log (evidence_id, user_id, action, detail, ip_address, user_agent, created_at)
         VALUES (:evidence_id, :user_id, :action, :detail, :ip_address, :user_agent, NOW())'
    );
    $stmt->execute([
        ':evidence_id' => $evidenceId,
        ':user_id'     => $userId,
        ':action'      => $action,
        ':detail'      => $detail,
        ':ip_address'  => $ip,
        ':user_agent'  => $userAgent,
    ]);
}

// ── Form helpers ────────────────────────────────────────────────────────────

/**
 * Retrieve an old form input value from the session ("sticky forms").
 *
 * After a failed form submission, save the submitted values to the session:
 *   $_SESSION['old'] = $_POST;
 * Then use old() in the form template to repopulate inputs so the user
 * does not have to retype everything.
 *
 * The stored values are NOT cleared by this function — clear them manually
 * (unset($_SESSION['old'])) once the form is successfully processed.
 *
 * Usage in a template:
 *   <input name="case_number" value="<?= e(old('case_number')) ?>">
 *
 * @param  string $key      The form field name (matches a key in $_SESSION['old'])
 * @param  string $default  Value to return if no old input exists for this key
 * @return string           HTML-safe old value, or $default
 */
function old(string $key, string $default = ''): string
{
    $value = $_SESSION['old'][$key] ?? $default;
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Return true if the current HTTP request was made with the POST method.
 *
 * Use this at the top of pages that handle both GET (show form) and
 * POST (process form) in the same file, to keep the logic clear:
 *
 *   if (isPostRequest()) {
 *       // handle form submission
 *   }
 *
 * @return bool
 */
function isPostRequest(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/**
 * Validate that all required fields are present and non-empty in a data array.
 *
 * Returns an associative array of validation errors keyed by field name.
 * An empty array means validation passed — all required fields are present.
 *
 * Usage:
 *   $errors = validateRequired($_POST, ['case_number', 'category', 'description']);
 *   if (!empty($errors)) {
 *       // re-show the form with error messages
 *   }
 *
 * @param  array<string, mixed> $data            The data to validate (typically $_POST)
 * @param  string[]             $requiredFields   List of field names that must be non-empty
 * @return array<string, string>                  Errors keyed by field name, empty if valid
 */
function validateRequired(array $data, array $requiredFields): array
{
    $errors = [];
    foreach ($requiredFields as $field) {
        // trim() strips whitespace so a field containing only spaces is treated as empty
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            // Convert field names like 'case_number' to 'Case number' for display
            $label = ucfirst(str_replace('_', ' ', $field));
            $errors[$field] = $label . ' is required.';
        }
    }
    return $errors;
}

// ── Miscellaneous ────────────────────────────────────────────────────────────

/**
 * Return a CSS class string for an evidence category badge.
 * Maps category names to a short slug safe for use in class names.
 *
 * @param  string $category  Raw category value from the database
 * @return string            CSS-safe slug, e.g. 'physical-evidence'
 */
function categorySlug(string $category): string
{
    return strtolower(str_replace([' ', '/'], ['-', '-'], $category));
}
