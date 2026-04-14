<?php
/**
 * evidence/view.php
 * Show a single evidence record in full detail.
 *
 * Displays: case details, metadata, notes, uploaded files, and audit trail.
 * Logs a 'viewed' audit entry every time the record is loaded.
 *
 * URL parameter: ?id=<evidence_id>
 */

// ── Bootstrap: load config/functions/auth before any output ──────────────────
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Guard: redirect unauthenticated visitors before any HTML is sent
requireLogin();

$pdo         = getDB();
$currentUser = currentUser();

// ── Validate the ?id= parameter ──────────────────────────────────────────────
// Do this before header.php so we can still redirect cleanly on bad input
$evidenceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$evidenceId || $evidenceId < 1) {
    setFlash('error', 'Invalid evidence record ID.');
    redirect('/dashboard/index.php');
}

// ── Load evidence record with joined user names ───────────────────────────────
$stmt = $pdo->prepare(
    'SELECT e.*,
            collector.full_name  AS collector_name,
            creator.full_name    AS created_by_name,
            updater.full_name    AS updated_by_name
     FROM   evidence e
     LEFT JOIN users collector ON collector.id = e.collector_id
     LEFT JOIN users creator   ON creator.id   = e.created_by
     LEFT JOIN users updater   ON updater.id   = e.updated_by
     WHERE  e.id = :id
     LIMIT  1'
);
$stmt->execute([':id' => $evidenceId]);
$evidence = $stmt->fetch();

// Show a friendly 404-style message if the ID doesn't exist
if (!$evidence) {
    setFlash('error', 'Evidence record not found.');
    redirect('/dashboard/index.php');
}

// Update page title to include the case number
$pageTitle = 'Evidence — ' . $evidence['case_number'];

// Now safe to output HTML — user is confirmed logged in and record exists
require_once __DIR__ . '/../includes/header.php';

// ── Load notes for this record ────────────────────────────────────────────────
$notes = $pdo->prepare(
    'SELECT n.note_text, n.created_at, u.full_name AS author
     FROM   evidence_notes n
     LEFT JOIN users u ON u.id = n.created_by
     WHERE  n.evidence_id = :id
     ORDER BY n.created_at ASC'
);
$notes->execute([':id' => $evidenceId]);
$noteRows = $notes->fetchAll();

// ── Load attached files ───────────────────────────────────────────────────────
$files = $pdo->prepare(
    'SELECT f.id, f.original_filename, f.stored_filename, f.mime_type,
            f.file_size, f.created_at, u.full_name AS uploader
     FROM   evidence_files f
     LEFT JOIN users u ON u.id = f.uploaded_by
     WHERE  f.evidence_id = :id
     ORDER BY f.created_at ASC'
);
$files->execute([':id' => $evidenceId]);
$fileRows = $files->fetchAll();

// ── Load audit trail for this record (most recent first) ─────────────────────
$audit = $pdo->prepare(
    'SELECT a.action, a.detail, a.ip_address, a.created_at, u.full_name AS actor
     FROM   audit_log a
     LEFT JOIN users u ON u.id = a.user_id
     WHERE  a.evidence_id = :id
     ORDER BY a.created_at DESC
     LIMIT 50'
);
$audit->execute([':id' => $evidenceId]);
$auditRows = $audit->fetchAll();

// ── Log a 'viewed' audit entry ────────────────────────────────────────────────
logAudit($pdo, $currentUser['id'], 'viewed', $evidenceId);

// ── Status badge colour helper ─────────────────────────────────────────────────
function statusBadgeStyle(string $status): string
{
    return match ($status) {
        'logged'      => 'background:#dbeafe; color:#1d4ed8;',
        'in_storage'  => 'background:#fef9c3; color:#854d0e;',
        'transferred' => 'background:#ede9fe; color:#6d28d9;',
        'analysed'    => 'background:#f0fdf4; color:#15803d;',
        'closed'      => 'background:#f1f5f9; color:#475569;',
        default       => 'background:#f1f5f9; color:#475569;',
    };
}
?>

<div class="container">

    <!-- ── Page header with action buttons ──────────────────────────────── -->
    <div class="page-header"
         style="margin-top:var(--space-4); display:flex; align-items:flex-start;
                justify-content:space-between; flex-wrap:wrap; gap:var(--space-3);">
        <div>
            <h1 class="page-title">Evidence Record</h1>
            <p class="page-subtitle">Case: <strong><?= e($evidence['case_number']) ?></strong></p>
        </div>
        <div style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/dashboard/index.php" class="btn btn-secondary">
                ← Dashboard
            </a>
            <!-- Edit link — full edit form is Phase 4 -->
            <a href="<?= BASE_URL ?>/evidence/edit.php?id=<?= (int) $evidence['id'] ?>"
               class="btn btn-outline">
                Edit Record
            </a>
        </div>
    </div>

    <!-- ── Evidence details card ─────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:var(--space-5);">
        <div class="card-header">
            <h2 class="card-title">Details</h2>
            <span class="badge" style="<?= statusBadgeStyle($evidence['status']) ?>">
                <?= e(ucfirst(str_replace('_', ' ', $evidence['status']))) ?>
            </span>
        </div>
        <div class="card-body">

            <!-- Metadata grid -->
            <div class="evidence-meta">
                <div class="meta-item">
                    <span class="meta-label">Case Number</span>
                    <span class="meta-value"><strong><?= e($evidence['case_number']) ?></strong></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Category</span>
                    <span class="meta-value">
                        <span class="badge badge-<?= e(categorySlug($evidence['category'])) ?>">
                            <?= e($evidence['category']) ?>
                        </span>
                    </span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Scene Location</span>
                    <span class="meta-value"><?= e($evidence['location'] ?? '—') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Collecting Officer</span>
                    <span class="meta-value"><?= e($evidence['collector_name'] ?? '—') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Collected At</span>
                    <span class="meta-value"><?= formatDate($evidence['collected_at']) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Submitted By</span>
                    <span class="meta-value"><?= e($evidence['created_by_name'] ?? '—') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Record Created</span>
                    <span class="meta-value"><?= formatDate($evidence['created_at']) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Last Updated</span>
                    <span class="meta-value"><?= formatDate($evidence['updated_at']) ?></span>
                </div>
            </div>

            <!-- Description -->
            <div style="margin-top:var(--space-4);">
                <span class="meta-label">Description</span>
                <p style="margin-top:var(--space-2); font-size:var(--font-size-sm);
                          color:var(--color-text); line-height:1.7; white-space:pre-wrap;">
                    <?= e($evidence['description']) ?>
                </p>
            </div>

        </div>
    </div>

    <!-- ── Notes card ────────────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:var(--space-5);">
        <div class="card-header">
            <h2 class="card-title">Notes</h2>
            <span style="font-size:var(--font-size-xs); color:var(--color-text-muted);">
                <?= count($noteRows) ?> note<?= count($noteRows) !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="card-body">
            <?php if (empty($noteRows)): ?>
                <p style="color:var(--color-text-muted); font-size:var(--font-size-sm);">
                    No notes recorded for this item.
                </p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:var(--space-4);">
                    <?php foreach ($noteRows as $note): ?>
                        <div style="padding:var(--space-4); background:var(--color-bg);
                                    border-radius:var(--radius-md);
                                    border-left:3px solid var(--color-primary);">
                            <p style="font-size:var(--font-size-sm); color:var(--color-text);
                                      margin:0 0 var(--space-2) 0; white-space:pre-wrap;">
                                <?= e($note['note_text']) ?>
                            </p>
                            <p style="font-size:var(--font-size-xs); color:var(--color-text-muted); margin:0;">
                                <?= e($note['author'] ?? 'Unknown') ?> &mdash;
                                <?= formatDate($note['created_at']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Attachments card ──────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:var(--space-5);">
        <div class="card-header">
            <h2 class="card-title">Attachments</h2>
            <span style="font-size:var(--font-size-xs); color:var(--color-text-muted);">
                <?= count($fileRows) ?> file<?= count($fileRows) !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="card-body">
            <?php if (empty($fileRows)): ?>
                <p style="color:var(--color-text-muted); font-size:var(--font-size-sm);">
                    No files attached to this record.
                </p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Uploaded By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fileRows as $file): ?>
                            <tr>
                                <td>
                                    <span style="font-size:var(--font-size-sm);">
                                        📎 <?= e($file['original_filename']) ?>
                                    </span>
                                </td>
                                <td class="td-muted"><?= e($file['mime_type']) ?></td>
                                <td class="td-muted"><?= formatFileSize((int) $file['file_size']) ?></td>
                                <td class="td-muted"><?= e($file['uploader'] ?? '—') ?></td>
                                <td class="td-muted"><?= formatDate($file['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Audit trail card ──────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:var(--space-8);">
        <div class="card-header">
            <h2 class="card-title">Chain of Custody — Audit Trail</h2>
            <span style="font-size:var(--font-size-xs); color:var(--color-text-muted);">
                Most recent first
            </span>
        </div>
        <div class="card-body">
            <?php if (empty($auditRows)): ?>
                <p style="color:var(--color-text-muted); font-size:var(--font-size-sm);">
                    No audit entries yet.
                </p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>User</th>
                                <th>Detail</th>
                                <th>IP Address</th>
                                <th>Date / Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditRows as $entry): ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background:var(--color-info-bg);
                                                               color:var(--color-info);">
                                        <?= e($entry['action']) ?>
                                    </span>
                                </td>
                                <td><?= e($entry['actor'] ?? '—') ?></td>
                                <td class="td-muted"
                                    style="max-width:260px; white-space:nowrap;
                                           overflow:hidden; text-overflow:ellipsis;">
                                    <?= e($entry['detail'] ?? '—') ?>
                                </td>
                                <td class="td-muted"><?= e($entry['ip_address'] ?? '—') ?></td>
                                <td class="td-muted"><?= formatDate($entry['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.container -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
