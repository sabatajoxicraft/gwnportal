<?php
/**
 * Central Migration Manager
 *
 * Modes:
 * - Audit only (default): php db/migrations/migration_manager.php --audit
 * - Apply pending managed SQL migrations: php db/migrations/migration_manager.php --apply
 *
 * Notes:
 * - Executes managed SQL files and records successful runs in _migrations.
 * - Migrations may include destructive operations (DROP TABLE, etc.); review each
 *   SQL file before running --apply in production.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/services/MigrationService.php';

$EXCLUDED_MIGRATIONS = [
    '2024_01_20_100000_add_logging_infrastructure.sql',
    '2026_01_17_100000_create_notifications.sql',
];

function printLine($message = '') {
    echo $message . PHP_EOL;
}

function findManagedSqlMigrations($dir) {
    global $EXCLUDED_MIGRATIONS;

    $all = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
    if ($all === false) {
        return [];
    }

    $managed = [];
    foreach ($all as $path) {
        $name = basename($path);

        if (in_array($name, $EXCLUDED_MIGRATIONS, true)) {
            continue;
        }

        // Keep all SQL files in db/migrations as managed migration artifacts.
        $managed[] = [
            'name' => $name,
            'path' => $path,
        ];
    }

    usort($managed, function ($a, $b) {
        return strnatcasecmp($a['name'], $b['name']);
    });

    return $managed;
}

function findExcludedSqlMigrations($dir) {
    global $EXCLUDED_MIGRATIONS;

    $all = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
    if ($all === false) {
        return [];
    }

    $excluded = [];
    foreach ($all as $path) {
        $name = basename($path);
        if (in_array($name, $EXCLUDED_MIGRATIONS, true)) {
            $excluded[] = $name;
        }
    }

    sort($excluded, SORT_NATURAL | SORT_FLAG_CASE);
    return $excluded;
}

function findUnmanagedMigrationEntrypoints($projectRoot) {
    $candidates = [
        $projectRoot . DIRECTORY_SEPARATOR . 'setup_db.php',
    ];

    $legacyApplyScripts = glob($projectRoot . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'apply_*.php');
    if ($legacyApplyScripts !== false) {
        foreach ($legacyApplyScripts as $path) {
            $candidates[] = $path;
        }
    }

    $archived = glob($projectRoot . DIRECTORY_SEPARATOR . 'archive' . DIRECTORY_SEPARATOR . '**' . DIRECTORY_SEPARATOR . 'run_migration.php');
    if ($archived !== false) {
        foreach ($archived as $path) {
            $candidates[] = $path;
        }
    }

    $existing = [];
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            $existing[] = $path;
        }
    }

    return array_values(array_unique($existing));
}

function runSqlMigration($conn, $path) {
    $sql = file_get_contents($path);
    if ($sql === false) {
        return [false, 'Failed to read file'];
    }

    if (!$conn->multi_query($sql)) {
        return [false, $conn->error];
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }

        if ($conn->errno) {
            return [false, $conn->error];
        }
    } while ($conn->next_result());

    if ($conn->errno) {
        return [false, $conn->error];
    }

    return [true, 'ok'];
}

$mode = '--audit';
if (in_array('--apply', $argv ?? [], true)) {
    $mode = '--apply';
}

$projectRoot = realpath(__DIR__ . '/../../');
$migrationDir = __DIR__;

printLine('==============================================');
printLine('Central Migration Manager');
printLine('Mode: ' . ($mode === '--apply' ? 'APPLY' : 'AUDIT'));
printLine('==============================================');
printLine();

$conn = getDbConnection();
if (!$conn) {
    printLine('ERROR: Could not connect to database');
    exit(1);
}

if (!MigrationService::init($conn)) {
    printLine('ERROR: Could not initialize MigrationService');
    exit(1);
}

$managed = findManagedSqlMigrations($migrationDir);
$excluded = findExcludedSqlMigrations($migrationDir);
$unmanagedEntrypoints = findUnmanagedMigrationEntrypoints($projectRoot);

printLine('Managed SQL migrations (db/migrations/*.sql): ' . count($managed));
foreach ($managed as $m) {
    $applied = MigrationService::isMigrationApplied($m['name']);
    $status = $applied ? 'tracked:yes' : 'tracked:no';
    printLine(' - ' . $m['name'] . ' [' . $status . ']');
}

printLine();
printLine('Unmanaged/legacy migration entrypoints: ' . count($unmanagedEntrypoints));
foreach ($unmanagedEntrypoints as $path) {
    $relative = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $path);
    printLine(' - ' . $relative);
}

printLine();
printLine('Excluded/deprecated SQL migrations: ' . count($excluded));
foreach ($excluded as $name) {
    printLine(' - ' . $name);
}

if ($mode === '--audit') {
    printLine();
    printLine('Audit complete. No changes were applied.');
    $conn->close();
    exit(0);
}

printLine();
printLine('Applying pending managed SQL migrations...');

$currentBatch = MigrationService::getCurrentBatch() + 1;
$appliedCount = 0;
$failedCount = 0;

foreach ($managed as $m) {
    $name = $m['name'];

    if (MigrationService::isMigrationApplied($name)) {
        printLine('SKIP  ' . $name . ' (already tracked)');
        continue;
    }

    printLine('APPLY ' . $name);
    list($ok, $msg) = runSqlMigration($conn, $m['path']);

    if ($ok) {
        MigrationService::recordMigration($name, $currentBatch);
        $appliedCount++;
        printLine('OK    ' . $name);
    } else {
        MigrationService::markMigrationFailed($name, $msg);
        $failedCount++;
        printLine('FAIL  ' . $name . ' => ' . $msg);
        printLine('Stopping on first failure to protect database consistency.');
        break;
    }
}

printLine();
printLine('Summary: applied=' . $appliedCount . ', failed=' . $failedCount . ', batch=' . $currentBatch);
printLine('Done.');

$conn->close();
exit($failedCount > 0 ? 1 : 0);
