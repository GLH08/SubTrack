#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/connect.php';

$requiredMigrations = [
    '000009_secure_password_reset_tokens.php',
    '000010_add_performance_indexes.php',
    '000011_add_subscriptions_sort_index.php',
];

$requiredIndexes = [
    'subscriptions' => [
        'idx_subscriptions_user_status_next_payment',
        'idx_subscriptions_user_status_created',
        'idx_subscriptions_user_next_payment_id',
    ],
    'payments' => [
        'idx_payments_subscription_paid_status',
    ],
    'notification_reads' => [
        'idx_notification_reads_user_sub_due',
    ],
];

function info(string $message): void
{
    echo "[INFO] {$message}\n";
}

function ok(string $message): void
{
    echo "[OK]   {$message}\n";
}

function fail(string $message): void
{
    echo "[FAIL] {$message}\n";
}

function hasPrefix(string $value, string $prefix): bool
{
    return strncmp($value, $prefix, strlen($prefix)) === 0;
}

/**
 * @return array<int, string>
 */
function scanPathRegression(string $projectRoot): array
{
    $projectRootNormalized = str_replace('\\', '/', rtrim($projectRoot, '/\\'));

    $excludeDirs = [
        '.git',
        'db',
        'docs',
        'node_modules',
        'vendor',
        'uploads',
    ];

    $excludeFiles = [
        'cli/preflight.php',
    ];

    $allowedExtensions = [
        'php', 'js', 'ts', 'tsx', 'json', 'yml', 'yaml', 'sh', 'md', 'toml', 'ini', 'xml',
    ];

    $allowedFileNames = [
        'Dockerfile', '.dockerignore', '.editorconfig', '.npmrc', '.nvmrc', '.gitignore',
    ];

    $exactNeedles = [
        'C:/Users/',
        'C:\\Users\\',
        'SubTrack/app',
        'Code/SubTrack/app',
        '\\SubTrack\\app',
        '\\Code\\SubTrack\\app',
        './app/',
        '../app/',
        'app/public',
        'app/api',
        'app/cli',
        'app/includes',
        'app/migrations',
        'app/cronjobs',
        'app/endpoints',
        'app/uploads',
        'app/db',
        '/app/public',
        '/app/api',
        '/app/cli',
        '/app/includes',
        '/app/migrations',
        '/app/cronjobs',
        '/app/endpoints',
        '/app/uploads',
        '/app/db',
        '.\\app\\',
        '..\\app\\',
        '\\app\\public',
        '\\app\\api',
        '\\app\\cli',
        '\\app\\includes',
        '\\app\\migrations',
        '\\app\\cronjobs',
        '\\app\\endpoints',
        '\\app\\uploads',
        '\\app\\db',
    ];

    $hits = [];
    $directory = new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::LEAVES_ONLY);

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $absolutePath = str_replace('\\', '/', $fileInfo->getPathname());
        $relativePath = ltrim(substr($absolutePath, strlen($projectRootNormalized)), '/');
        if ($relativePath === '') {
            continue;
        }

        $skip = false;
        foreach ($excludeDirs as $excluded) {
            if ($relativePath === $excluded || hasPrefix($relativePath, $excluded . '/')) {
                $skip = true;
                break;
            }
        }
        if (!$skip && in_array($relativePath, $excludeFiles, true)) {
            $skip = true;
        }
        if ($skip) {
            continue;
        }

        $baseName = $fileInfo->getBasename();
        $extension = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
        if (!in_array($baseName, $allowedFileNames, true) && !in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $content = @file_get_contents($fileInfo->getPathname());
        if (!is_string($content) || $content === '') {
            continue;
        }

        $matched = false;
        foreach ($exactNeedles as $needle) {
            if (strpos($content, $needle) !== false) {
                $matched = true;
                break;
            }
        }

        if ($matched) {
            $hits[] = $relativePath;
        }
    }

    return $hits;
}

$hardFailures = 0;
$warnings = 0;

try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    fail('Database connection failed: ' . $e->getMessage());
    exit(1);
}

info('Running SubTrack beta preflight checks');

$appDebug = getenv('APP_DEBUG');
if ($appDebug === false || $appDebug === '') {
    ok('APP_DEBUG is not set (treated as disabled)');
} elseif ($appDebug === '0') {
    ok('APP_DEBUG=0');
} else {
    fail('APP_DEBUG must be 0 or unset in public beta. Current value: ' . $appDebug);
    $hardFailures++;
}

$hasMigrationsTable = false;
try {
    $tableStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations' LIMIT 1");
    $hasMigrationsTable = (bool) $tableStmt->fetchColumn();
} catch (Throwable $e) {
    $hasMigrationsTable = false;
}

if (!$hasMigrationsTable) {
    fail('migrations table not found. Run migration script first.');
    $hardFailures++;
} else {
    $stmt = $pdo->query('SELECT filename FROM migrations');
    $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($requiredMigrations as $filename) {
        if (in_array($filename, $executed, true)) {
            ok('Migration applied: ' . $filename);
        } else {
            fail('Missing migration: ' . $filename);
            $hardFailures++;
        }
    }
}

foreach ($requiredIndexes as $table => $indexes) {
    $indexRows = $pdo->query("PRAGMA index_list({$table})")->fetchAll(PDO::FETCH_ASSOC);
    $existingIndexes = array_map(static fn(array $row): string => (string) ($row['name'] ?? ''), $indexRows);

    foreach ($indexes as $indexName) {
        if (in_array($indexName, $existingIndexes, true)) {
            ok("Index exists: {$table}.{$indexName}");
        } else {
            fail("Missing index: {$table}.{$indexName}");
            $hardFailures++;
        }
    }
}

try {
    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($users <= 0) {
        fail('No users found in database. Seed or import data before launch.');
        $warnings++;
    } else {
        ok('User count check passed: ' . $users);
    }
} catch (Throwable $e) {
    fail('User count check failed: ' . $e->getMessage());
    $warnings++;
}

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fail('Path regression check failed: unable to resolve project root');
    $hardFailures++;
} else {
    $pathRegressionHits = scanPathRegression($projectRoot);
    if (!empty($pathRegressionHits)) {
        fail('Detected legacy app-path or Windows absolute path references:');
        foreach (array_slice($pathRegressionHits, 0, 20) as $hit) {
            fail('  - ' . $hit);
        }
        if (count($pathRegressionHits) > 20) {
            fail('  ... and ' . (count($pathRegressionHits) - 20) . ' more file(s)');
        }
        $hardFailures++;
    } else {
        ok('Path regression check passed (no app/ or Windows hardcoded paths)');
    }
}

echo "\n";
if ($hardFailures > 0) {
    fail("Preflight failed with {$hardFailures} hard failure(s), {$warnings} warning(s)");
    exit(1);
}

ok("Preflight passed with {$warnings} warning(s)");
exit(0);
