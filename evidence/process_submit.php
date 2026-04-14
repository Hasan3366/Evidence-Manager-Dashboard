<?php
/**
 * evidence/process_submit.php
 * Handles the evidence submission form POST.
 *
 * This file:
 *  1. Verifies the request is POST and the user is logged in
 *  2. Validates the CSRF token
 *  3. Validates all required fields
 *  4. Inserts the evidence row
 *  5. Inserts non-empty notes into evidence_notes
 *  6. Handles file uploads into evidence_files
 *  7. Writes entries to audit_log
 *  8. Redirects to view.php on success or back to submit.php on failure
 *
 * Never send output directly — only redirect.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// ── Guard: POST only ──────────────────────────────────────────────────────────
if (!isPostRequest()) {
    setFlash('error', 'Invalid request method.');
    redirect('/evidence/submit.php');
}

// ── Guard: must be logged in ──────────────────────────────────────────────────
if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$currentUser = currentUser();

// ── CSRF check ────────────────────────────────────────────────────────────────
$submittedToken = trim((string) ($_POST['csrf_token'] ?? ''));
if (!verifyCsrfToken($submittedToken)) {
    setFlash('error', 'Your session expired. Please try again.');
    redirect('/evidence/submit.php');
}

// ── Collect and trim all text inputs ─────────────────────────────────────────
$caseNumber   = trim((string) ($_POST['case_number']   ?? ''));
$category     = trim((string) ($_POST['category']      ?? ''));
$status       = trim((string) ($_POST['status']        ?? ''));
$description  = trim((string) ($_POST['description']   ?? ''));
$location     = trim((string) ($_POST['location']      ?? ''));
$collectedAt  = trim((string) ($_POST['collected_at']  ?? ''));
$collectorId  = trim((string) ($_POST['collector_id']  ?? ''));
$notes        = $_POST['notes'] ?? [];   // array of note strings

// ── Validate required fields ──────────────────────────────────────────────────
$errors = [];

if ($caseNumber === '') {
    $errors['case_number'] = 'Case number is required.';
}

if ($category === '') {
    $errors['category'] = 'Category is required.';
} elseif (!in_array($category, EVIDENCE_CATEGORIES, true)) {
    // Reject any value not in the official list — prevents tampered POST data
    $errors['category'] = 'Invalid category selected.';
}

if ($status === '') {
    $errors['status'] = 'Status is required.';
} elseif (!in_array($status, EVIDENCE_STATUSES, true)) {
    $errors['status'] = 'Invalid status selected.';
}

if ($description === '') {
    $errors['description'] = 'Description is required.';
}

if ($collectedAt === '') {
    $errors['collected_at'] = 'Collection date and time is required.';
}

if ($collectorId === '' || !ctype_digit($collectorId)) {
    $errors['collector_id'] = 'Please select a collecting officer.';
}

// ── Convert datetime-local value to MySQL DATETIME format ────────────────────
// Browser sends "2026-04-14T10:30", MySQL expects "2026-04-14 10:30:00"
$collectedAtMysql = null;
if ($collectedAt !== '') {
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $collectedAt);
    if ($dt === false) {
        $errors['collected_at'] = 'Invalid date/time format.';
    } else {
        $collectedAtMysql = $dt->format('Y-m-d H:i:s');
    }
}

// ── If validation failed, store errors and old input, redirect back ───────────
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    // Preserve the submitted text so form fields can be repopulated (old())
    $_SESSION['old'] = $_POST;
    setFlash('error', 'Please correct the errors below and resubmit.');
    redirect('/evidence/submit.php');
}

// ── Validate collector exists in users table ──────────────────────────────────
$pdo = getDB();
$collectorCheck = $pdo->prepare(
    'SELECT id FROM users WHERE id = :id AND active = 1 LIMIT 1'
);
$collectorCheck->execute([':id' => (int) $collectorId]);
if (!$collectorCheck->fetch()) {
    setFlash('error', 'Selected officer not found. Please try again.');
    $_SESSION['old'] = $_POST;
    redirect('/evidence/submit.php');
}

// ── Insert evidence record ────────────────────────────────────────────────────
// Wrap everything in a transaction so partial inserts are rolled back on error
$pdo->beginTransaction();

try {
    $stmtEvidence = $pdo->prepare(
        'INSERT INTO evidence
            (case_number, category, description, location, collector_id,
             collected_at, status, created_by, created_at, updated_at)
         VALUES
            (:case_number, :category, :description, :location, :collector_id,
             :collected_at, :status, :created_by, NOW(), NOW())'
    );
    $stmtEvidence->execute([
        ':case_number'  => $caseNumber,
        ':category'     => $category,
        ':description'  => $description,
        ':location'     => $location !== '' ? $location : null,
        ':collector_id' => (int) $collectorId,
        ':collected_at' => $collectedAtMysql,
        ':status'       => $status,
        ':created_by'   => $currentUser['id'],
    ]);

    // Get the auto-generated ID for the new evidence row
    $evidenceId = (int) $pdo->lastInsertId();

    // ── Write audit entry: evidence created ──────────────────────────────────
    logAudit(
        $pdo,
        $currentUser['id'],
        'created',
        $evidenceId,
        "Case {$caseNumber} — {$category}"
    );

    // ── Insert notes ─────────────────────────────────────────────────────────
    $stmtNote = $pdo->prepare(
        'INSERT INTO evidence_notes (evidence_id, note_text, created_by, created_at)
         VALUES (:evidence_id, :note_text, :created_by, NOW())'
    );

    foreach ($notes as $noteText) {
        $noteText = trim((string) $noteText);
        // Skip blank notes — the user may have left extra boxes empty
        if ($noteText === '') {
            continue;
        }
        $stmtNote->execute([
            ':evidence_id' => $evidenceId,
            ':note_text'   => $noteText,
            ':created_by'  => $currentUser['id'],
        ]);
        logAudit($pdo, $currentUser['id'], 'note_added', $evidenceId, mb_substr($noteText, 0, 100));
    }

    // ── Handle file uploads ───────────────────────────────────────────────────
    $uploadErrors = [];

    // $_FILES['evidence_files'] is an array-of-arrays when multiple="multiple"
    if (!empty($_FILES['evidence_files']['name'][0])) {

        $stmtFile = $pdo->prepare(
            'INSERT INTO evidence_files
                (evidence_id, original_filename, stored_filename, file_path,
                 mime_type, file_size, uploaded_by, created_at)
             VALUES
                (:evidence_id, :original_filename, :stored_filename, :file_path,
                 :mime_type, :file_size, :uploaded_by, NOW())'
        );

        $fileCount = count($_FILES['evidence_files']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            // Skip empty slots (browser sends an empty entry when nothing chosen)
            if ($_FILES['evidence_files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            // Check for PHP upload errors
            if ($_FILES['evidence_files']['error'][$i] !== UPLOAD_ERR_OK) {
                $uploadErrors[] = "Upload error on file " . ($i + 1) . ".";
                continue;
            }

            $originalName = $_FILES['evidence_files']['name'][$i];
            $tmpPath      = $_FILES['evidence_files']['tmp_name'][$i];
            $fileSize     = (int) $_FILES['evidence_files']['size'][$i];

            // Enforce maximum file size
            if ($fileSize > MAX_UPLOAD_SIZE) {
                $uploadErrors[] = e($originalName) . ' exceeds the maximum allowed size of ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB.';
                continue;
            }

            // ── Validate file extension ───────────────────────────────────
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
                $uploadErrors[] = e($originalName) . ' — file type not allowed.';
                continue;
            }

            // ── Validate actual MIME type via finfo (do NOT trust browser) ─
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpPath);
            if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
                $uploadErrors[] = e($originalName) . ' — file content does not match an allowed type.';
                continue;
            }

            // ── Generate a random stored filename ─────────────────────────
            // Never use the original name on disk — prevents path traversal
            // and executable file attacks.
            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
            $destPath   = UPLOAD_DIR . $storedName;
            $filePath   = 'uploads/evidence/' . $storedName; // relative path for DB

            // Move from PHP tmp location to the evidence uploads directory
            if (!move_uploaded_file($tmpPath, $destPath)) {
                $uploadErrors[] = 'Failed to save ' . e($originalName) . '. Please try again.';
                continue;
            }

            // Insert the file record
            $stmtFile->execute([
                ':evidence_id'      => $evidenceId,
                ':original_filename'=> $originalName,
                ':stored_filename'  => $storedName,
                ':file_path'        => $filePath,
                ':mime_type'        => $mimeType,
                ':file_size'        => $fileSize,
                ':uploaded_by'      => $currentUser['id'],
            ]);

            logAudit(
                $pdo,
                $currentUser['id'],
                'file_uploaded',
                $evidenceId,
                $originalName . ' (' . formatFileSize($fileSize) . ')'
            );
        }
    }

    // Commit: evidence row, notes, and file records all saved
    $pdo->commit();

    // Clear preserved old input on success
    unset($_SESSION['old']);
    // Rotate CSRF token after successful submit
    unset($_SESSION['csrf_token']);

    // Report any non-fatal upload issues as a warning
    if (!empty($uploadErrors)) {
        setFlash('warning', 'Evidence saved, but some files were not uploaded: ' . implode(' ', $uploadErrors));
    } else {
        setFlash('success', 'Evidence record submitted successfully.');
    }

    redirect('/evidence/view.php?id=' . $evidenceId);

} catch (Throwable $e) {
    // Roll back database changes so we do not have partial records
    $pdo->rollBack();

    // Log the real error server-side — never expose it to the user
    error_log('[Evidence Manager] process_submit error: ' . $e->getMessage());

    setFlash('error', 'Unable to save the evidence record. Please try again.');
    $_SESSION['old'] = $_POST;
    redirect('/evidence/submit.php');
}
