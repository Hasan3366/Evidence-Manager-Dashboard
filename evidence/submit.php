<?php
/**
 * evidence/submit.php
 * Evidence submission form.
 *
 * Allows logged-in officers and admins to log a new piece of evidence.
 * Uses CSRF protection, sticky form values (old()), and server-side
 * validation. File uploads and notes are handled by process_submit.php.
 */

// ── Bootstrap: load config/functions/auth before any output ──────────────────
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Guard: redirect unauthenticated visitors before any HTML is sent
requireLogin();

$pdo  = getDB();
$user = currentUser();

// Set page metadata — header.php reads these variables
$pageTitle = 'Submit Evidence';

// Now safe to output HTML — user is confirmed logged in
require_once __DIR__ . '/../includes/header.php';

// Generate a CSRF token for this form
$csrfToken = generateCsrfToken();

// Load all active users for the "Collector" dropdown
$officers = $pdo->query(
    "SELECT id, full_name, badge_number
     FROM users
     WHERE active = 1
     ORDER BY full_name ASC"
)->fetchAll();

// Pull any validation errors stored by process_submit.php on failure
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);

// ── Repopulate notes from previous failed submission ─────────────────────────
// $_SESSION['old']['notes'] is the raw notes[] array the user submitted.
// Filter out blank entries so we do not render empty extra rows.
$oldNotes = [];
if (!empty($_SESSION['old']['notes']) && is_array($_SESSION['old']['notes'])) {
    foreach ($_SESSION['old']['notes'] as $n) {
        $trimmed = trim((string) $n);
        if ($trimmed !== '') {
            $oldNotes[] = $trimmed;
        }
    }
}
// Always keep at least one slot so the first row is always rendered
if (empty($oldNotes)) {
    $oldNotes = [''];
}
?>

<div class="container">

    <div class="page-header" style="margin-top: var(--space-4);">
        <h1 class="page-title">Submit Evidence</h1>
        <p class="page-subtitle">Record a new item of evidence against a case number. All fields marked <span style="color:var(--color-primary)">*</span> are required.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <!-- Validation error summary at the top of the form -->
        <div class="alert alert-error" role="alert" style="margin-bottom:var(--space-5);">
            <div>
                <strong>Please fix the following errors:</strong>
                <ul style="margin: var(--space-2) 0 0 var(--space-5);">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="post"
          action="<?= BASE_URL ?>/evidence/process_submit.php"
          enctype="multipart/form-data"
          class="needs-validation"
          novalidate>

        <!-- CSRF token — must be present in every POST form -->
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <!-- ── Section 1: Case details ──────────────────────────────────── -->
        <div class="card" style="margin-bottom: var(--space-5);">
            <div class="card-header">
                <h2 class="card-title">Case Details</h2>
            </div>
            <div class="card-body">
                <div class="form-grid">

                    <!-- Case number -->
                    <div class="form-group">
                        <label class="form-label" for="case_number">
                            Case Number <span class="required">*</span>
                        </label>
                        <input type="text"
                               id="case_number"
                               name="case_number"
                               class="form-control <?= isset($errors['case_number']) ? 'is-invalid' : '' ?>"
                               value="<?= old('case_number') ?>"
                               maxlength="50"
                               placeholder="e.g. CRX-2026-00142"
                               required>
                        <?php if (isset($errors['case_number'])): ?>
                            <span class="form-error"><?= e($errors['case_number']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Category dropdown -->
                    <div class="form-group">
                        <label class="form-label" for="category">
                            Category <span class="required">*</span>
                        </label>
                        <select id="category" name="category"
                                class="form-control <?= isset($errors['category']) ? 'is-invalid' : '' ?>"
                                required>
                            <option value="">— Select category —</option>
                            <?php foreach (EVIDENCE_CATEGORIES as $cat): ?>
                                <option value="<?= e($cat) ?>"
                                    <?= old('category') === $cat ? 'selected' : '' ?>>
                                    <?= e($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['category'])): ?>
                            <span class="form-error"><?= e($errors['category']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Status dropdown -->
                    <div class="form-group">
                        <label class="form-label" for="status">
                            Status <span class="required">*</span>
                        </label>
                        <select id="status" name="status"
                                class="form-control <?= isset($errors['status']) ? 'is-invalid' : '' ?>"
                                required>
                            <?php foreach (EVIDENCE_STATUSES as $st): ?>
                                <option value="<?= e($st) ?>"
                                    <?= (old('status', 'logged') === $st) ? 'selected' : '' ?>>
                                    <?= e(ucfirst(str_replace('_', ' ', $st))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <span class="form-error"><?= e($errors['status']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Location -->
                    <div class="form-group">
                        <label class="form-label" for="location">Scene Location</label>
                        <input type="text"
                               id="location"
                               name="location"
                               class="form-control"
                               value="<?= old('location') ?>"
                               maxlength="255"
                               placeholder="e.g. 14 High Street, London">
                    </div>

                    <!-- Collector -->
                    <div class="form-group">
                        <label class="form-label" for="collector_id">
                            Collecting Officer <span class="required">*</span>
                        </label>
                        <select id="collector_id" name="collector_id"
                                class="form-control <?= isset($errors['collector_id']) ? 'is-invalid' : '' ?>"
                                required>
                            <option value="">— Select officer —</option>
                            <?php foreach ($officers as $officer): ?>
                                <?php
                                // Default to the currently logged-in user
                                $selected = ((int) old('collector_id', (string) $user['id'])) === (int) $officer['id'];
                                ?>
                                <option value="<?= (int) $officer['id'] ?>"
                                    <?= $selected ? 'selected' : '' ?>>
                                    <?= e($officer['full_name']) ?>
                                    <?= $officer['badge_number'] ? '(' . e($officer['badge_number']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['collector_id'])): ?>
                            <span class="form-error"><?= e($errors['collector_id']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Collection datetime -->
                    <div class="form-group">
                        <label class="form-label" for="collected_at">
                            Date / Time Collected <span class="required">*</span>
                        </label>
                        <input type="datetime-local"
                               id="collected_at"
                               name="collected_at"
                               class="form-control <?= isset($errors['collected_at']) ? 'is-invalid' : '' ?>"
                               value="<?= old('collected_at', date('Y-m-d\TH:i')) ?>"
                               required>
                        <?php if (isset($errors['collected_at'])): ?>
                            <span class="form-error"><?= e($errors['collected_at']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Description — spans both columns -->
                    <div class="form-group span-full">
                        <label class="form-label" for="description">
                            Description <span class="required">*</span>
                        </label>
                        <textarea id="description"
                                  name="description"
                                  class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                                  rows="4"
                                  placeholder="Describe the evidence item in detail…"
                                  required><?= old('description') ?></textarea>
                        <?php if (isset($errors['description'])): ?>
                            <span class="form-error"><?= e($errors['description']) ?></span>
                        <?php endif; ?>
                    </div>

                </div><!-- /.form-grid -->
            </div><!-- /.card-body -->
        </div><!-- /.card -->

        <!-- ── Section 2: Notes ─────────────────────────────────────────── -->
        <div class="card" style="margin-bottom: var(--space-5);">
            <div class="card-header">
                <h2 class="card-title">Notes</h2>
                <span class="page-subtitle">Add one or more chain-of-custody notes.</span>
            </div>
            <div class="card-body">
                <div id="notes-container">
                    <?php foreach ($oldNotes as $i => $noteValue): ?>
                    <!-- Note row <?= $i + 1 ?> — remove button hidden on first row -->
                    <div class="form-group note-row">
                        <label class="form-label">Note</label>
                        <div style="display:flex; gap:var(--space-3); align-items:flex-start;">
                            <textarea name="notes[]"
                                      class="form-control"
                                      rows="2"
                                      placeholder="<?= $i === 0 ? 'Enter an initial note about this evidence item…' : 'Enter a note…' ?>"><?= e($noteValue) ?></textarea>
                            <!-- Hide the remove button on the first row only -->
                            <button type="button"
                                    class="btn btn-outline btn-sm remove-note"
                                    style="<?= $i === 0 ? 'display:none; ' : '' ?>flex-shrink:0; margin-top:2px;"
                                    aria-label="Remove note">✕</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Add another note -->
                <button type="button" id="add-note" class="btn btn-secondary btn-sm">
                    + Add Another Note
                </button>
            </div>
        </div>

        <!-- ── Section 3: File attachments ─────────────────────────────── -->
        <div class="card" style="margin-bottom: var(--space-5);">
            <div class="card-header">
                <h2 class="card-title">Attachments</h2>
                <span class="page-subtitle">Photographs, PDFs, or video clips. Max <?= MAX_UPLOAD_SIZE / 1024 / 1024 ?> MB each.</span>
            </div>
            <div class="card-body">

                <?php if (isset($errors['files'])): ?>
                    <div class="alert alert-error" style="margin-bottom:var(--space-4);">
                        <?= e($errors['files']) ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-file-label" for="evidence_files">
                        <span style="font-size:1.5rem;">📎</span>
                        <div>
                            <strong>Choose files</strong>
                            <div class="form-hint" style="margin-top:2px;">
                                JPG, PNG, GIF, WebP, PDF, MP4, MOV — up to <?= MAX_UPLOAD_SIZE / 1024 / 1024 ?> MB each
                            </div>
                        </div>
                    </label>
                    <!-- multiple allows selecting several files at once -->
                    <input type="file"
                           id="evidence_files"
                           name="evidence_files[]"
                           multiple
                           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.mp4,.mov">
                    <!-- JS will populate this span with chosen filenames -->
                    <span id="file-chosen" class="form-hint" style="margin-top:var(--space-2); display:block;"></span>
                </div>

            </div>
        </div>

        <!-- ── Submit bar ──────────────────────────────────────────────── -->
        <div style="display:flex; gap:var(--space-3); justify-content:flex-end; margin-bottom:var(--space-8);">
            <a href="<?= BASE_URL ?>/dashboard/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Submit Evidence</button>
        </div>

    </form>
</div><!-- /.container -->

<script>
/* ── Add / remove dynamic note rows ─────────────────────────── */
document.getElementById('add-note').addEventListener('click', function () {
    const container = document.getElementById('notes-container');
    const row = document.createElement('div');
    row.className = 'form-group note-row';
    row.innerHTML = `
        <label class="form-label">Note</label>
        <div style="display:flex; gap:var(--space-3); align-items:flex-start;">
            <textarea name="notes[]" class="form-control" rows="2"
                      placeholder="Enter a note…"></textarea>
            <button type="button"
                    class="btn btn-outline btn-sm remove-note"
                    style="flex-shrink:0; margin-top:2px;"
                    aria-label="Remove note">✕</button>
        </div>`;
    container.appendChild(row);
});

document.getElementById('notes-container').addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-note')) {
        e.target.closest('.note-row').remove();
    }
});

/* ── Show chosen filenames under the file input ─────────────── */
document.getElementById('evidence_files').addEventListener('change', function () {
    const span = document.getElementById('file-chosen');
    const files = Array.from(this.files).map(f => f.name);
    span.textContent = files.length
        ? files.join(', ')
        : '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
