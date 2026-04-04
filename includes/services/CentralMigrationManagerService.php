<?php
/**
 * Central Migration Manager Service
 *
 * Shared migration manager logic for CLI/web entrypoints.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/MigrationService.php';

class CentralMigrationManagerService
{
    /**
     * Legacy or destructive migrations that are excluded from managed apply flow.
     */
    private static $EXCLUDED_MIGRATIONS = [
        '2024_01_20_100000_add_logging_infrastructure.sql',
        '2026_01_17_100000_create_notifications.sql',
    ];

    /**
     * Build migration inventory and tracking status.
     */
    public static function audit($conn, $projectRoot, $migrationDir)
    {
        MigrationService::init($conn);

        $managed = self::findManagedSqlMigrations($migrationDir);
        foreach ($managed as &$m) {
            $m['tracked'] = MigrationService::isMigrationApplied($m['name']);
        }
        unset($m);

        return [
            'managed' => $managed,
            'unmanaged_entrypoints' => self::findUnmanagedMigrationEntrypoints($projectRoot),
            'excluded_migrations' => self::findExcludedMigrationFiles($migrationDir),
        ];
    }

    /**
     * Apply pending managed SQL migrations and record successful runs.
     */
    public static function applyPending($conn, $projectRoot, $migrationDir)
    {
        MigrationService::init($conn);

        $managed = self::findManagedSqlMigrations($migrationDir);
        $currentBatch = MigrationService::getCurrentBatch() + 1;

        $summary = [
            'batch' => $currentBatch,
            'applied' => 0,
            'failed' => 0,
            'events' => [],
        ];

        foreach ($managed as $m) {
            $name = $m['name'];

            if (MigrationService::isMigrationApplied($name)) {
                $summary['events'][] = [
                    'status' => 'skip',
                    'migration' => $name,
                    'message' => 'already tracked',
                ];
                continue;
            }

            list($ok, $msg) = self::runSqlMigration($conn, $m['path']);

            if ($ok) {
                MigrationService::recordMigration($name, $currentBatch);
                $summary['applied']++;
                $summary['events'][] = [
                    'status' => 'ok',
                    'migration' => $name,
                    'message' => 'applied and tracked',
                ];
                continue;
            }

            MigrationService::markMigrationFailed($name, $msg);
            $summary['failed']++;
            $summary['events'][] = [
                'status' => 'fail',
                'migration' => $name,
                'message' => $msg,
            ];

            // Stop at first failure to protect consistency.
            break;
        }

        return $summary;
    }

    private static function findManagedSqlMigrations($dir)
    {
        $all = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
        if ($all === false) {
            return [];
        }

        $managed = [];
        foreach ($all as $path) {
            $name = basename($path);
            if (in_array($name, self::$EXCLUDED_MIGRATIONS, true)) {
                continue;
            }

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

    private static function findUnmanagedMigrationEntrypoints($projectRoot)
    {
        $candidates = [
            $projectRoot . DIRECTORY_SEPARATOR . 'setup_db.php',
        ];

        $legacyApplyScripts = glob($projectRoot . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'apply_*.php');
        if ($legacyApplyScripts !== false) {
            foreach ($legacyApplyScripts as $path) {
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

    private static function findExcludedMigrationFiles($dir)
    {
        $all = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
        if ($all === false) {
            return [];
        }

        $excluded = [];
        foreach ($all as $path) {
            $name = basename($path);
            if (in_array($name, self::$EXCLUDED_MIGRATIONS, true)) {
                $excluded[] = $name;
            }
        }

        sort($excluded, SORT_NATURAL | SORT_FLAG_CASE);
        return $excluded;
    }

    private static function runSqlMigration($conn, $path)
    {
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
}
