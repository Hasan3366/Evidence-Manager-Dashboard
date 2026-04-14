<?php
/**
 * dashboard/index.php
 * Main dashboard — summary stats, search/filter, and evidence table.
 *
 * Supports GET parameters:
 *   q            — keyword / case number search
 *   category     — filter by category
 *   status       — filter by status
 *   collector_id — filter by collecting officer
 *   date_from    — records created on/after this date (YYYY-MM-DD)
 *   date_to      — records created on/before this date (YYYY-MM-DD)
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
$pageTitle = 'Dashboard';
$bodyClass = '';

// Now safe to output HTML — user is confirmed logged in
require_once __DIR__ . '/../includes/header.php';

// ── Read and sanitize GET filter parameters ───────────────────────────────────
$filterQ           = trim((string) ($_GET['q']            ?? ''));
$filterCategory    = trim((string) ($_GET['category']     ?? ''));
$filterStatus      = trim((string) ($_GET['status']       ?? ''));
$filterCollectorId = trim((string) ($_GET['collector_id'] ?? ''));
$filterDateFrom    = trim((string) ($_GET['date_from']    ?? ''));
$filterDateTo      = trim((string) ($_GET['date_to']      ?? ''));

// Reject values that are not in the allowed lists
if (!in_array($filterCategory, EVIDENCE_CATEGORIES, true)) {
    $filterCategory = '';
}
if (!in_array($filterStatus, EVIDENCE_STATUSES, true)) {
    $filterStatus = '';
}
if ($filterCollectorId !== '' && !ctype_digit($filterCollectorId)) {
    $filterCollectorId = '';
}
// Validate dates
if ($filterDateFrom !== '' && !DateTime::createFromFormat('Y-m-d', $filterDateFrom)) {
    $filterDateFrom = '';
}
if ($filterDateTo !== '' && !DateTime::createFromFormat('Y-m-d', $filterDateTo)) {
    $filterDateTo = '';
}

// ── Summary statistics (always unfiltered) ────────────────────────────────────
$totalEvidence = (int) $pdo->query('SELECT COUNT(*) FROM evidence')->fetchColumn();
$todayCount    = (int) $pdo->query(
    "SELECT COUNT(*) FROM evidence WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();
$openCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM evidence WHERE status NOT IN ('closed','analysed')"
)->fetchColumn();
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE active = 1')->fetchColumn();

// ── Status breakdown (for sidebar) ───────────────────────────────────────────
$statusRows = $pdo->query(
    'SELECT status, COUNT(*) AS cnt FROM evidence GROUP BY status ORDER BY cnt DESC'
)->fetchAll();

// ── Load active officers for collector filter dropdown ────────────────────────
$officers = $pdo->query(
    'SELECT id, full_name FROM users WHERE active = 1 ORDER BY full_name ASC'
)->fetchAll();

// ── Build filtered evidence query dynamically ─────────────────────────────────
// Using an array of conditions and params prevents SQL injection
$conditions = [];
$params     = [];

if ($filterQ !== '') {
    // Search case number or description
    $conditions[] = '(e.case_number LIKE :q OR e.description LIKE :q2)';
    $params[':q']  = '%' . $filterQ . '%';
    $params[':q2'] = '%' . $filterQ . '%';
}
if ($filterCategory !== '') {
    $conditions[] = 'e.category = :category';
    $params[':category'] = $filterCategory;
}
if ($filterStatus !== '') {
    $conditions[] = 'e.status = :status';
    $params[':status'] = $filterStatus;
}
if ($filterCollectorId !== '') {
    $conditions[] = 'e.collector_id = :collector_id';
    $params[':collector_id'] = (int) $filterCollectorId;
}
if ($filterDateFrom !== '') {
    $conditions[] = 'DATE(e.created_at) >= :date_from';
    $params[':date_from'] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $conditions[] = 'DATE(e.created_at) <= :date_to';
    $params[':date_to'] = $filterDateTo;
}

$whereClause = !empty($conditions)
    ? 'WHERE ' . implode(' AND ', $conditions)
    : '';

$sql = "SELECT e.id, e.case_number, e.category, e.status, e.created_at,
               u.full_name AS submitted_by,
               c.full_name AS collector_name
        FROM evidence e
        LEFT JOIN users u ON u.id = e.created_by
        LEFT JOIN users c ON c.id = e.collector_id
        {$whereClause}
        ORDER BY e.created_at DESC
        LIMIT 50";

$stmtEvidence = $pdo->prepare($sql);
$stmtEvidence->execute($params);
$evidenceRows = $stmtEvidence->fetchAll();

// Is any filter active? — used to show "clear filters" link
$filtersActive = ($filterQ || $filterCategory || $filterStatus
               || $filterCollectorId || $filterDateFrom || $filterDateTo);
?>

<div class="container">

    <!-- Page header -->
    <div class="page-header" style="margin-top:var(--space-4); display:flex;
         align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:var(--space-3);">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">
                Welcome back, <strong><?= e($user['full_name']) ?></strong>.
                Here is today&rsquo;s evidence overview.
            </p>
        </div>
        <a href="<?= BASE_URL ?>/evidence/submit.php" class="btn btn-primary">
            + Submit Evidence
        </a>
    </div>

    <!-- ── Stat cards ──────────────────────────────────────────────────────── -->
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

    <!-- ── Main content + sidebar ──────────────────────────────────────────── -->
    <div style="display:grid; grid-template-columns:1fr 260px; gap:var(--space-6); align-items:start;">

        <!-- Evidence table -->
        <div>

            <!-- Search / filter bar -->
            <form method="get" action="<?= BASE_URL ?>/dashboard/index.php"
                  class="filter-bar" style="margin-bottom:var(--space-5);">

                <!-- Keyword search -->
                <div class="filter-group" style="flex:2; min-width:200px;">
                    <label class="filter-label" for="q">Search</label>
                    <input type="text"
                           id="q"
                           name="q"
                           class="form-control"
                           value="<?= e($filterQ) ?>"
                           placeholder="Case number or keyword&hellip;">
                </div>

                <!-- Category -->
                <div class="filter-group">
                    <label class="filter-label" for="f-category">Category</label>
                    <select id="f-category" name="category" class="form-control">
                        <option value="">All categories</option>
                        <?php foreach (EVIDENCE_CATEGORIES as $cat): ?>
                            <option value="<?= e($cat) ?>"
                                <?= $filterCategory === $cat ? 'selected' : '' ?>>
                                <?= e($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div class="filter-group">
                    <label class="filter-label" for="f-status">Status</label>
                    <select id="f-status" name="status" class="form-control">
                        <option value="">All statuses</option>
                        <?php foreach (EVIDENCE_STATUSES as $st): ?>
                            <option value="<?= e($st) ?>"
                                <?= $filterStatus === $st ? 'selected' : '' ?>>
                                <?= e(ucfirst(str_replace('_', ' ', $st))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Collector -->
                <div class="filter-group">
                    <label class="filter-label" for="f-collector">Officer</label>
                    <select id="f-collector" name="collector_id" class="form-control">
                        <option value="">All officers</option>
                        <?php foreach ($officers as $o): ?>
                            <option value="<?= (int) $o['id'] ?>"
                                <?= (string) $filterCollectorId === (string) $o['id'] ? 'selected' : '' ?>>
                                <?= e($o['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date from -->
                <div class="filter-group">
                    <label class="filter-label" for="f-from">From</label>
                    <input type="date" id="f-from" name="date_from"
                           class="form-control"
                           value="<?= e($filterDateFrom) ?>">
                </div>

                <!-- Date to -->
                <div class="filter-group">
                    <label class="filter-label" for="f-to">To</label>
                    <input type="date" id="f-to" name="date_to"
                           class="form-control"
                           value="<?= e($filterDateTo) ?>">
                </div>

                <!-- Buttons -->
                <div class="filter-group" style="align-self:flex-end; flex:0; min-width:auto;">
                    <div style="display:flex; gap:var(--space-2);">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        <?php if ($filtersActive): ?>
                            <a href="<?= BASE_URL ?>/dashboard/index.php"
                               class="btn btn-secondary btn-sm">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>

            </form>

            <!-- Evidence table card -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        Evidence Records
                        <?php if ($filtersActive): ?>
                            <span style="font-size:var(--font-size-sm); font-weight:400;
                                         color:var(--color-text-muted);">
                                — filtered
                            </span>
                        <?php endif; ?>
                    </h2>
                    <span style="font-size:var(--font-size-xs); color:var(--color-text-muted);">
                        <?= count($evidenceRows) ?> record<?= count($evidenceRows) !== 1 ? 's' : '' ?>
                        <?= $filtersActive ? 'shown' : '(latest 50)' ?>
                    </span>
                </div>

                <?php if (empty($evidenceRows)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <p class="empty-state-title">No evidence records found</p>
                        <p class="empty-state-text">
                            <?php if ($filtersActive): ?>
                                Try adjusting the filters above, or
                                <a href="<?= BASE_URL ?>/dashboard/index.php">clear all filters</a>.
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/evidence/submit.php">Submit the first evidence record</a>
                                to get started.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Case #</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Collector</th>
                                    <th>Submitted By</th>
                                    <th>Date</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evidenceRows as $row): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/evidence/view.php?id=<?= (int) $row['id'] ?>"
                                           style="font-weight:600; color:var(--color-text);">
                                            <?= e($row['case_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= e(categorySlug($row['category'])) ?>">
                                            <?= e($row['category']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge" style="<?= statusStyle($row['status']) ?>">
                                            <?= e(ucfirst(str_replace('_', ' ', $row['status']))) ?>
                                        </span>
                                    </td>
                                    <td class="td-muted"><?= e($row['collector_name'] ?? '—') ?></td>
                                    <td class="td-muted"><?= e($row['submitted_by'] ?? '—') ?></td>
                                    <td class="td-muted"><?= formatDate($row['created_at'], 'd M Y') ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/evidence/view.php?id=<?= (int) $row['id'] ?>"
                                           class="btn btn-secondary btn-sm">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div><!-- /.card -->

        </div><!-- /evidence column -->

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
                    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
                    <?php foreach ($statusRows as $s): ?>
                        <?php $pct = $totalEvidence > 0
                            ? round(($s['cnt'] / $totalEvidence) * 100)
                            : 0; ?>
                        <div>
                            <div style="display:flex; justify-content:space-between;
                                        font-size:var(--font-size-xs); margin-bottom:4px;">
                                <span style="font-weight:600; color:var(--color-text);">
                                    <?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?>
                                </span>
                                <span style="color:var(--color-text-muted);">
                                    <?= (int) $s['cnt'] ?> (<?= $pct ?>%)
                                </span>
                            </div>
                            <div style="height:6px; background:var(--color-border);
                                        border-radius:var(--radius-full); overflow:hidden;">
                                <div style="height:100%; width:<?= $pct ?>%;
                                            background:var(--color-primary);
                                            border-radius:var(--radius-full);"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick links -->
            <div class="card-footer">
                <div style="display:flex; flex-direction:column; gap:var(--space-2);">
                    <a href="<?= BASE_URL ?>/evidence/submit.php"
                       class="btn btn-primary btn-sm btn-block">+ Submit Evidence</a>
                    <?php if (hasRole(ROLE_ADMIN)): ?>
                    <a href="<?= BASE_URL ?>/users/index.php"
                       class="btn btn-secondary btn-sm btn-block">Manage Users</a>
                    <a href="<?= BASE_URL ?>/audit/index.php"
                       class="btn btn-secondary btn-sm btn-block">Audit Log</a>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /sidebar -->

    </div><!-- /grid -->

</div><!-- /.container -->

<style>
/* Responsive: stack sidebar below table on smaller screens */
@media (max-width: 900px) {
    div[style*="grid-template-columns:1fr 260px"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php
/**
 * Return inline badge style for a given evidence status.
 * Defined here so view.php can also use it without requiring a shared helper.
 */
function statusStyle(string $status): string
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
