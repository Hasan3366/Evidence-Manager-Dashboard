<?php
/**
 * dashboard/index.php
 * Main dashboard — summary stats and recent evidence entries.
 *
 * Protected: requires an active login session.
 */

$pageTitle = 'Dashboard';
$bodyClass = '';

require_once __DIR__ . '/../includes/header.php';

// Redirect guests to the login page
requireLogin();

$pdo  = getDB();
$user = currentUser();

// ── Summary statistics ────────────────────────────────────────────────────────

// Total evidence records
$totalEvidence = (int) $pdo->query('SELECT COUNT(*) FROM evidence')->fetchColumn();

// Evidence logged today
$todayCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM evidence WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();

// Open items (not yet closed or analysed)
$openCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM evidence WHERE status NOT IN ('closed','analysed')"
)->fetchColumn();

// Total registered users (admin stat)
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE active = 1')->fetchColumn();

// ── Recent evidence (last 10 entries) ────────────────────────────────────────
$recentStmt = $pdo->query(
    'SELECT e.id, e.case_number, e.category, e.status, e.created_at,
            u.full_name AS submitted_by
     FROM evidence e
     LEFT JOIN users u ON u.id = e.created_by
     ORDER BY e.created_at DESC
     LIMIT 10'
);
$recentEvidence = $recentStmt->fetchAll();

// ── Status breakdown ──────────────────────────────────────────────────────────
$statusRows  = $pdo->query(
    'SELECT status, COUNT(*) AS cnt FROM evidence GROUP BY status ORDER BY cnt DESC'
)->fetchAll();
?>

<div class="container">

    <!-- Page header -->
    <div class="page-header" style="margin-top: var(--space-4);">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">
            Welcome back, <strong><?= e($user['full_name']) ?></strong>.
            Here is today's evidence overview.
        </p>
    </div>

    <!-- ── Stat cards ─────────────────────────────────────────────────────── -->
    <div class="stat-grid">

        <div class="stat-card">
            <span class="stat-label">Total Evidence</span>
            <span class="stat-value"><?= $totalEvidence ?></span>
            <span class="stat-detail">All records on file</span>
        </div>

        <div class="stat-card">
            <span class="stat-label">Logged Today</span>
            <span class="stat-value"><?= $todayCount ?></span>
            <span class="stat-detail"><?= date('d M Y') ?></span>
        </div>

        <div class="stat-card">
            <span class="stat-label">Open Items</span>
            <span class="stat-value"><?= $openCount ?></span>
            <span class="stat-detail">Not yet closed or analysed</span>
        </div>

        <?php if (hasRole(ROLE_ADMIN)): ?>
        <div class="stat-card">
            <span class="stat-label">Active Users</span>
            <span class="stat-value"><?= $userCount ?></span>
            <span class="stat-detail">Registered officers</span>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Main content row ───────────────────────────────────────────────── -->
    <div style="display:grid; grid-template-columns: 1fr 280px; gap: var(--space-6); align-items: start;">

        <!-- Recent evidence table -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Evidence</h2>
                <a href="<?= BASE_URL ?>/evidence/submit.php" class="btn btn-primary btn-sm">
                    + Submit Evidence
                </a>
            </div>

            <?php if (empty($recentEvidence)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <p class="empty-state-title">No evidence logged yet</p>
                    <p class="empty-state-text">Submit the first evidence record to get started.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Case #</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Submitted By</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEvidence as $row): ?>
                            <tr>
                                <td><strong><?= e($row['case_number']) ?></strong></td>
                                <td>
                                    <span class="badge badge-<?= e(categorySlug($row['category'])) ?>">
                                        <?= e($row['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-status-<?= e($row['status']) ?>">
                                        <?= e(ucfirst(str_replace('_', ' ', $row['status']))) ?>
                                    </span>
                                </td>
                                <td class="td-muted"><?= e($row['submitted_by'] ?? '—') ?></td>
                                <td class="td-muted"><?= formatDate($row['created_at']) ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="<?= BASE_URL ?>/evidence/view.php?id=<?= (int)$row['id'] ?>"
                                           class="btn btn-secondary btn-sm">View</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($totalEvidence > 10): ?>
            <div class="card-footer" style="text-align:right;">
                <a href="<?= BASE_URL ?>/evidence/index.php" class="btn btn-outline btn-sm">
                    View all <?= $totalEvidence ?> records →
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Status breakdown sidebar -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">By Status</h2>
            </div>
            <div class="card-body">
                <?php if (empty($statusRows)): ?>
                    <p style="color:var(--color-text-muted); font-size:var(--font-size-sm);">
                        No data yet.
                    </p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:var(--space-3);">
                    <?php foreach ($statusRows as $s): ?>
                        <?php
                            $pct = $totalEvidence > 0
                                ? round(($s['cnt'] / $totalEvidence) * 100)
                                : 0;
                        ?>
                        <div>
                            <div style="display:flex; justify-content:space-between;
                                        font-size:var(--font-size-xs); margin-bottom:4px;">
                                <span style="font-weight:600; color:var(--color-text);">
                                    <?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?>
                                </span>
                                <span style="color:var(--color-text-muted);">
                                    <?= (int)$s['cnt'] ?> (<?= $pct ?>%)
                                </span>
                            </div>
                            <div style="height:6px; background:var(--color-border);
                                        border-radius:var(--radius-full); overflow:hidden;">
                                <div style="height:100%; width:<?= $pct ?>%;
                                            background:var(--color-primary);
                                            border-radius:var(--radius-full);
                                            transition:width 0.4s ease;">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.grid -->

</div><!-- /.container -->

<style>
/* Status badge colours */
.badge-status-logged      { background:#dbeafe; color:#1d4ed8; }
.badge-status-in_storage  { background:#fef9c3; color:#854d0e; }
.badge-status-transferred { background:#ede9fe; color:#6d28d9; }
.badge-status-analysed    { background:#f0fdf4; color:#15803d; }
.badge-status-closed      { background:#f1f5f9; color:#475569; }

/* Responsive two-column → single column on tablet */
@media (max-width: 900px) {
    div[style*="grid-template-columns: 1fr 280px"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
