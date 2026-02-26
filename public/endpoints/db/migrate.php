<?php
/**
 * Database Migration Script
 * Runs all migration files in order
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/connect.php';

echo "Starting database migration...\n";

try {
    $pdo = getDbConnection();

    // Get all migration files
    $migrationDir = __DIR__ . '/../../../migrations';
    $migrationFiles = glob($migrationDir . '/*.php');

    if (empty($migrationFiles)) {
        echo "No migration files found.\n";
        exit(0);
    }

    sort($migrationFiles);

    // Create migrations tracking table if not exists
    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL UNIQUE,
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    // Get already executed migrations
    $executedMigrations = [];
    try {
        $stmt = $pdo->query('SELECT filename FROM migrations');
        $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Table might not exist yet, that's ok
        echo "  [INFO] Migrations table error: " . $e->getMessage() . "\n";
    }

    $executedCount = 0;

    foreach ($migrationFiles as $file) {
        $filename = basename($file);

        // Skip if already executed
        if (in_array($filename, $executedMigrations, true)) {
            echo "  [SKIP] {$filename} (already executed)\n";
            continue;
        }

        echo "  [RUN]  {$filename}...\n";

        try {
            $pdo->beginTransaction();

            // Execute migration
            require $file;

            // Record migration
            $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (:filename)');
            $stmt->execute([':filename' => $filename]);

            $pdo->commit();

            echo "  [OK]   {$filename}\n";
            $executedCount++;

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "  [FAIL] {$filename}: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    echo "\nMigration completed successfully!\n";
    echo "Executed {$executedCount} migration(s).\n";

    exit(0);

} catch (Exception $e) {
    echo "\nMigration failed: " . $e->getMessage() . "\n";
    exit(1);
}
