<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/services/CentralMigrationManagerService.php';

requireRole('admin');

$pageTitle = 'Migration Manager';
$activePage = 'admin-migrations';

$conn = getDbConnection();
$projectRoot = realpath(__DIR__ . '/../../');
$migrationDir = $projectRoot . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'migrations';

$flashSuccess = '';
$flashError = '';
$applySummary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    if (isset($_POST['run_apply'])) {
        $applySummary = CentralMigrationManagerService::applyPending($conn, $projectRoot, $migrationDir);
        if ($applySummary['failed'] > 0) {
            $flashError = 'Migration apply stopped due to a failure. Review results below.';
        } else {
            $flashSuccess = 'Pending managed migrations applied successfully.';
        }

        logActivity($conn, $_SESSION['user_id'], 'admin_migration_apply', 'Admin ran migration manager apply from UI', $_SERVER['REMOTE_ADDR'] ?? '');
    }

    if (isset($_POST['run_audit'])) {
        $flashSuccess = 'Migration audit refreshed.';
    }
}

$audit = CentralMigrationManagerService::audit($conn, $projectRoot, $migrationDir);
$managed = $audit['managed'];
$unmanaged = $audit['unmanaged_entrypoints'];
$excluded = $audit['excluded_migrations'] ?? [];

require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Migration Manager</h2>
        <a href="<?= BASE_URL ?>/profile.php" class="btn btn-outline-secondary btn-sm">Back to Profile</a>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Actions</div>
        <div class="card-body">
            <form method="post" class="d-inline-block me-2">
                <?= csrfField() ?>
                <button type="submit" name="run_audit" value="1" class="btn btn-primary">Run Audit</button>
            </form>
            <form method="post" class="d-inline-block" onsubmit="return confirm('Apply pending managed migrations? This updates schema/data and cannot be automatically undone.');">
                <?= csrfField() ?>
                <button type="submit" name="run_apply" value="1" class="btn btn-warning">Apply Pending Migrations</button>
            </form>
            <p class="text-muted mt-3 mb-0">
                Managed migrations are SQL files in <code>db/migrations/*.sql</code>. Apply mode records successful runs in <code>_migrations</code>.
            </p>
        </div>
    </div>

    <?php if (is_array($applySummary)): ?>
        <div class="card mb-4">
            <div class="card-header">Last Apply Result</div>
            <div class="card-body">
                <p class="mb-2"><strong>Batch:</strong> <?= (int)$applySummary['batch'] ?></p>
                <p class="mb-2"><strong>Applied:</strong> <?= (int)$applySummary['applied'] ?></p>
                <p class="mb-3"><strong>Failed:</strong> <?= (int)$applySummary['failed'] ?></p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Migration</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applySummary['events'] as $event): ?>
                                <tr>
                                    <td><?= htmlspecialchars(strtoupper($event['status'])) ?></td>
                                    <td><code><?= htmlspecialchars($event['migration']) ?></code></td>
                                    <td><?= htmlspecialchars($event['message']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Managed SQL Migrations</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Migration</th>
                            <th>Tracking Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($managed as $m): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($m['name']) ?></code></td>
                                <td>
                                    <?php if (!empty($m['tracked'])): ?>
                                        <span class="badge bg-success">Tracked</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Tracked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Excluded / Deprecated SQL Migrations</div>
        <div class="card-body">
            <?php if (empty($excluded)): ?>
                <p class="mb-0 text-muted">No excluded migrations configured.</p>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($excluded as $name): ?>
                        <li><code><?= htmlspecialchars($name) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Unmanaged / Legacy Entrypoints</div>
        <div class="card-body">
            <?php if (empty($unmanaged)): ?>
                <p class="mb-0 text-muted">No unmanaged entrypoints found.</p>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($unmanaged as $path): ?>
                        <li><code><?= htmlspecialchars(str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $path)) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
