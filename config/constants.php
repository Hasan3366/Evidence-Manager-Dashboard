<?php
/**
 * config/constants.php
 * Application-wide constants — loaded by every page via header.php.
 *
 * Edit APP_NAME and BASE_URL to match your setup.
 */

// ── Application identity ─────────────────────────────────────────────────────
define('APP_NAME', 'Evidence Manager');

// Base URL of the application (no trailing slash).
// Leave empty ''  if the app lives at the web root  (e.g. http://localhost/).
// Set to '/evidence-manager'  if it lives in a subfolder.
define('BASE_URL', '');

// Absolute filesystem path to the project root directory
define('ROOT_PATH', dirname(__DIR__));
// ─────────────────────────────────────────────────────────────────────────────

// ── User roles ───────────────────────────────────────────────────────────────
define('ROLE_ADMIN',   'admin');
define('ROLE_OFFICER', 'officer');
// ─────────────────────────────────────────────────────────────────────────────

// ── Evidence categories ──────────────────────────────────────────────────────
// IMPORTANT: These string values are stored verbatim in the `evidence`.`category`
// column. Once records exist in the database, renaming a value here will cause
// existing rows to display an unrecognised category.
// To rename a category safely, you must also run an UPDATE on existing rows.
//
// Each value is the canonical display label and the database-stored value.
// Use these in SELECT dropdowns, filter bars, and badge rendering.
// The helper function categorySlug() in functions.php converts these to
// CSS-safe class names (e.g. 'Physical Evidence' → 'physical-evidence').
define('EVIDENCE_CATEGORIES', [
    'Physical Evidence', // Tangible objects found at the scene (clothing, tools, weapons, etc.)
    'Digital Media',     // Electronic storage devices, phones, computers, memory cards
    'Documents',         // Paper records, printed materials, handwritten notes
    'Biological',        // DNA samples, blood, hair, bodily fluids — handle with sterile equipment
    'Firearms',          // Guns, ammunition, casings — follow authorised handling procedures
    'Narcotics',         // Controlled substances — must be sealed and weighed per procedure
    'Vehicle',           // Motor vehicles or vehicle components associated with the scene
    'Other',             // Any evidence that does not fit the above categories
]);
// ─────────────────────────────────────────────────────────────────────────────

// ── Evidence statuses ────────────────────────────────────────────────────────
// These are the only values that should be accepted for the `evidence`.`status`
// column. Server-side validation on submit and edit forms must use
// in_array($submitted_status, EVIDENCE_STATUSES, true) to reject any value
// that is not in this list — never trust or store a status string that was
// submitted by the user without checking it against this constant first.
//
// IMPORTANT: Like categories, these values are stored verbatim in the database.
// Renaming a value after records exist requires a corresponding UPDATE migration.
define('EVIDENCE_STATUSES', [
    'logged',       // Initial state — item has been recorded at the scene
    'in_storage',   // Item has been physically moved to an evidence storage facility
    'transferred',  // Item has been transferred to another officer, lab, or agency
    'analysed',     // Item has been examined or tested by forensics / a specialist
    'closed',       // Case or exhibit is closed — no further action required
]);
// ─────────────────────────────────────────────────────────────────────────────

// ── File upload settings ─────────────────────────────────────────────────────

// Absolute filesystem path to the directory where uploaded evidence files are stored.
// This directory must exist and be writable by the web server process.
// It must NOT be directly accessible over HTTP — the .htaccess in that folder
// blocks all direct requests, so files must be served via a PHP controller.
define('UPLOAD_DIR', ROOT_PATH . '/uploads/evidence/');

// Internal URL prefix used when building links to serve files through PHP.
// Files are NOT accessed at this URL directly — it is used by the serving
// controller (e.g. evidence/file.php?id=X) to look up the correct stored path.
define('UPLOAD_URL', BASE_URL . '/uploads/evidence/');

// ── WHY WE CHECK BOTH MIME TYPE AND FILE EXTENSION ───────────────────────────
// Checking only the file extension is NOT safe. An attacker can rename a PHP
// script to "photo.jpg" and upload it. If the server executes it, they gain
// remote code execution. Checking only the MIME type is also insufficient — the
// MIME type submitted in the HTTP request is controlled by the client and can
// be spoofed trivially (e.g. sending "image/jpeg" for a malicious file).
//
// The correct approach is to check BOTH:
//   1. The file extension — using pathinfo($filename, PATHINFO_EXTENSION)
//      against ALLOWED_EXTENSIONS below.
//   2. The actual MIME type — using PHP's finfo_file() to inspect the file
//      content itself, NOT $_FILES['file']['type'], and comparing against
//      ALLOWED_MIME_TYPES below.
//
// Both checks must pass for a file to be accepted. This makes it significantly
// harder for an attacker to upload a file that can be executed by the server.
// ─────────────────────────────────────────────────────────────────────────────

// Permitted MIME types — verified by reading actual file content via finfo.
// Do NOT use $_FILES['file']['type'] — that value comes from the browser
// and can be set to anything by the uploader.
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',       // .jpg / .jpeg  — scene photographs
    'image/png',        // .png          — screenshots, diagrams
    'image/gif',        // .gif          — rarely used but accepted
    'image/webp',       // .webp         — modern image format from mobile devices
    'application/pdf',  // .pdf          — statements, reports, printed documents
    'video/mp4',        // .mp4          — body cam footage, CCTV clips
    'video/quicktime',  // .mov          — iPhone / camera video files
]);

// Permitted file extensions — checked via pathinfo() on the original filename.
// Must stay in sync with ALLOWED_MIME_TYPES above so that each accepted
// extension maps to an accepted MIME type and vice versa.
define('ALLOWED_EXTENSIONS', [
    'jpg', 'jpeg',  // JPEG images
    'png',          // PNG images
    'gif',          // GIF images
    'webp',         // WebP images
    'pdf',          // PDF documents
    'mp4',          // MP4 video
    'mov',          // QuickTime video
]);

// Maximum permitted file size per upload, in bytes.
// Default: 20 MB — suitable for photographs and short video clips.
// Increase only if your server's upload_max_filesize and post_max_size
// php.ini settings also allow it.
define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024); // 20 MB
// ─────────────────────────────────────────────────────────────────────────────

// ── Pagination ───────────────────────────────────────────────────────────────
// Number of evidence records shown per page on the dashboard
define('RECORDS_PER_PAGE', 20);
// ─────────────────────────────────────────────────────────────────────────────
