<?php
/**
 * evidence/submit.php
 * Submit a new evidence record.
 *
 * Phase 3 — full form will be built here.
 * For now this is a working placeholder so navigation links resolve.
 */

$pageTitle = 'Submit Evidence';

require_once __DIR__ . '/../includes/header.php';

requireLogin();
?>

<div class="container">

    <div class="page-header" style="margin-top: var(--space-4);">
        <h1 class="page-title">Submit Evidence</h1>
        <p class="page-subtitle">Log a new piece of evidence against a case number.</p>
    </div>

    <div class="card">
        <div class="card-body" style="text-align:center; padding: var(--space-12) var(--space-8);">
            <div class="empty-state-icon" style="font-size:3rem; margin-bottom:var(--space-4);">📋</div>
            <h2 class="empty-state-title" style="font-size:var(--font-size-lg); font-weight:600; margin-bottom:var(--space-2);">
                Evidence Submission Form
            </h2>
            <p style="color:var(--color-text-muted); font-size:var(--font-size-sm); margin-bottom:var(--space-6);">
                This form is being built in Phase 3. Check back soon.
            </p>
            <a href="<?= BASE_URL ?>/dashboard/index.php" class="btn btn-secondary">
                ← Back to Dashboard
            </a>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
