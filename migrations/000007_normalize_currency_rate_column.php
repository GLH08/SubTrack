<?php

declare(strict_types=1);

$pdo = getDbConnection();

function hasColumn(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query("PRAGMA table_info($table)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        if (($col['name'] ?? '') === $column) {
            return true;
        }
    }

    return false;
}

$hasRateToCny = hasColumn($pdo, 'currencies', 'rate_to_cny');
$hasExchangeRate = hasColumn($pdo, 'currencies', 'exchange_rate');

if (!$hasRateToCny) {
    $pdo->exec('ALTER TABLE currencies ADD COLUMN rate_to_cny REAL DEFAULT 1');
    echo "Added column: rate_to_cny\n";
}

if ($hasExchangeRate) {
    $pdo->exec('UPDATE currencies SET rate_to_cny = COALESCE(NULLIF(rate_to_cny, 0), exchange_rate, 1)');
    echo "Backfilled rate_to_cny from exchange_rate\n";
} else {
    $pdo->exec('UPDATE currencies SET rate_to_cny = COALESCE(NULLIF(rate_to_cny, 0), 1)');
}

echo "Migration completed successfully.\n";
