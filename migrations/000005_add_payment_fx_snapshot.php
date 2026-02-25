<?php
/**
 * Migration: Add FX snapshot fields to payments
 * Locks historical exchange rates at payment creation time to prevent history drift
 */

declare(strict_types=1);

$pdo = getDbConnection();

$fields = [
    'fx_rate_to_cny' => 'REAL',
    'amount_cny' => 'REAL',
    'fx_source' => 'TEXT',
    'fx_locked_at' => 'TEXT',
];

foreach ($fields as $field => $type) {
    try {
        $pdo->exec("ALTER TABLE payments ADD COLUMN {$field} {$type}");
        echo "Added column: {$field}\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'duplicate column name') === false) {
            echo "Warning: {$field} - {$e->getMessage()}\n";
        }
    }
}

// Backfill old records with current currency rate snapshot
$pdo->exec("UPDATE payments SET fx_rate_to_cny = (SELECT COALESCE(c.rate_to_cny, 1) FROM currencies c WHERE c.id = payments.currency_id) WHERE fx_rate_to_cny IS NULL OR fx_rate_to_cny <= 0");
$pdo->exec("UPDATE payments SET amount_cny = amount * COALESCE(fx_rate_to_cny, 1) WHERE amount_cny IS NULL OR amount_cny <= 0");
$pdo->exec("UPDATE payments SET fx_source = 'backfill_current_rate' WHERE fx_source IS NULL OR fx_source = ''");
$pdo->exec("UPDATE payments SET fx_locked_at = COALESCE(created_at, paid_at, datetime('now')) WHERE fx_locked_at IS NULL OR fx_locked_at = ''");

echo "Migration completed successfully.\n";
