<?php
/**
 * audit/index.php
 * Audit log viewer — admin only.
 *
 * Phase 3 — full filtered view will be built here.
 * For now this is a working placeholder that shows recent audit entries.
 */

$pageTitle = 'Audit Log';

require_once __DIR__ . '/../includes/header.php';

requireLogin();
requireAdmin();

$pdo  = getDB();
$rows = $pdo->query(
    'SELECT a.id, a.action, a.detail, a.ip_address, a.created_at,
            u.full_name AS actor,
            a.evidence_id
     FROM audit_log a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 50'
)->fetchAll();
?>

<div class="container">

    <div class="page-header" style="margin-top: var(--space-4);">
        <h1 class="page-title">Audit Log</h1>
        <p class="page-subtitle">Chain of custody — all system activity is recorded here.</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Activity</h2>
            <span class="badge" style="background:var(--color-primary-light); color:var(--color-primary);">
                Last 50 entries
            </span>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <p class="empty-state-title">No audit entries yet</p>
                <p class="empty-state-text">Activity will appear here once users interact with evidence records.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Evidence ID</th>
                            <th>Detail</th>
                            <th>IP</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="td-muted"><?= (int)$row['id'] ?></td>
                            <td><strong><?= e($row['actor'] ?? '—') ?></strong></td>
                            <td>
                                <span class="badge" style="background:var(--color-info-bg); color:var(--color-info);">
                                    <?= e($row['action']) ?>
                                </span>
                            </td>
                            <td class="td-muted">
                                <?= $row['evidence_id'] ? '#' . (int)$row['evidence_id'] : '—' ?>
                            </td>
                            <td class="td-muted" style="max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= e($row['detail'] ?? '—') ?>
                            </td>
                            <td class="td-muted"><?= e($row['ip_address'] ?? '—') ?></td>
                            <td class="td-muted"><?= formatDate($row['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="card-footer" style="font-size:var(--font-size-xs); color:var(--color-text-muted);">
            Full search and export will be available in Phase 3.
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
