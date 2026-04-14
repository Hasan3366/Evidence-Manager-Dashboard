<?php
/**
 * users/index.php
 * User management — admin only.
 *
 * Phase 3 — full CRUD will be built here.
 * For now this is a working placeholder so navigation links resolve.
 */

$pageTitle = 'User Management';

require_once __DIR__ . '/../includes/header.php';

requireLogin();
requireAdmin();

$pdo   = getDB();
$users = $pdo->query(
    'SELECT id, username, full_name, role, badge_number, active, created_at
     FROM users
     ORDER BY created_at DESC'
)->fetchAll();
?>

<div class="container">

    <div class="page-header" style="margin-top: var(--space-4);">
        <h1 class="page-title">User Management</h1>
        <p class="page-subtitle">View and manage officer and administrator accounts.</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">All Users</h2>
            <span class="badge" style="background:var(--color-primary-light); color:var(--color-primary);">
                <?= count($users) ?> registered
            </span>
        </div>

        <?php if (empty($users)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">👤</div>
                <p class="empty-state-title">No users found</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Badge</th>
                            <th>Status</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong><?= e($u['full_name']) ?></strong></td>
                            <td class="td-muted"><?= e($u['username']) ?></td>
                            <td>
                                <span class="nav-user-badge role-<?= e($u['role']) ?>">
                                    <?= ucfirst(e($u['role'])) ?>
                                </span>
                            </td>
                            <td class="td-muted"><?= e($u['badge_number'] ?? '—') ?></td>
                            <td>
                                <?php if ($u['active']): ?>
                                    <span class="badge" style="background:var(--color-success-bg); color:var(--color-success);">Active</span>
                                <?php else: ?>
                                    <span class="badge" style="background:var(--color-error-bg); color:var(--color-error);">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-muted"><?= formatDate($u['created_at'], 'd M Y') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="card-footer" style="font-size:var(--font-size-xs); color:var(--color-text-muted);">
            Full user creation and editing will be available in Phase 3.
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
