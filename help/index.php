<?php
/**
 * help/index.php
 * Help and reference page — all logged-in users.
 */

$pageTitle = 'Help';

require_once __DIR__ . '/../includes/header.php';

requireLogin();
?>

<div class="container">

    <div class="page-header" style="margin-top: var(--space-4);">
        <h1 class="page-title">Help &amp; Reference</h1>
        <p class="page-subtitle">Guidance on using the <?= e(APP_NAME) ?> system correctly.</p>
    </div>

    <!-- Quick-start section -->
    <div class="help-section">
        <h2 class="help-section-title">Getting Started</h2>
        <div class="help-list">
            <div class="help-item">
                <div class="help-item-icon">🔐</div>
                <div class="help-item-text">
                    <strong>Login &amp; Sessions</strong>
                    Sign in with your assigned username and password.
                    Sessions expire when you close your browser.
                    Always log out on shared computers.
                </div>
            </div>
            <div class="help-item">
                <div class="help-item-icon">📋</div>
                <div class="help-item-text">
                    <strong>Submitting Evidence</strong>
                    Use <em>Submit Evidence</em> in the nav bar to log a new item.
                    Fill in the case number, category, description, and collection details.
                    Attach photos, PDFs, or video files where available.
                </div>
            </div>
            <div class="help-item">
                <div class="help-item-icon">🔍</div>
                <div class="help-item-text">
                    <strong>Searching Records</strong>
                    Use the filter bar on the evidence list to search by case number,
                    category, status, or date range.
                </div>
            </div>
        </div>
    </div>

    <!-- Evidence statuses section -->
    <div class="help-section">
        <h2 class="help-section-title">Evidence Statuses</h2>
        <div class="help-list">
            <?php
            $statuses = [
                'logged'      => 'Item has been recorded at the scene — initial state.',
                'in_storage'  => 'Item has been moved to an evidence storage facility.',
                'transferred' => 'Item has been transferred to another officer, lab, or agency.',
                'analysed'    => 'Item has been examined or tested.',
                'closed'      => 'Case or exhibit is closed — no further action required.',
            ];
            foreach ($statuses as $key => $desc):
            ?>
            <div class="help-item">
                <div class="help-item-icon">
                    <span class="badge badge-status-<?= e($key) ?>" style="font-size:0.7rem;">
                        <?= e(ucfirst(str_replace('_', ' ', $key))) ?>
                    </span>
                </div>
                <div class="help-item-text"><?= e($desc) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Roles section -->
    <div class="help-section">
        <h2 class="help-section-title">User Roles</h2>
        <div class="help-list">
            <div class="help-item">
                <div class="help-item-icon">🛡️</div>
                <div class="help-item-text">
                    <strong>Administrator</strong>
                    Can manage users, view the audit log, submit and edit all evidence records,
                    and configure system settings.
                </div>
            </div>
            <div class="help-item">
                <div class="help-item-icon">👮</div>
                <div class="help-item-text">
                    <strong>Officer</strong>
                    Can submit evidence and view records assigned to their cases.
                    Cannot access user management or the audit log.
                </div>
            </div>
        </div>
    </div>

    <!-- Contact section -->
    <div class="help-section">
        <h2 class="help-section-title">Support</h2>
        <div class="card">
            <div class="card-body">
                <p style="font-size:var(--font-size-sm); color:var(--color-text-muted);">
                    For technical issues contact your system administrator.
                    All activity in this system is logged for chain-of-custody compliance.
                </p>
            </div>
        </div>
    </div>

</div>

<style>
.badge-status-logged      { background:#dbeafe; color:#1d4ed8; }
.badge-status-in_storage  { background:#fef9c3; color:#854d0e; }
.badge-status-transferred { background:#ede9fe; color:#6d28d9; }
.badge-status-analysed    { background:#f0fdf4; color:#15803d; }
.badge-status-closed      { background:#f1f5f9; color:#475569; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
