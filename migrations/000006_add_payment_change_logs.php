<?php
/**
 * Migration: Add payment change audit fields and logs table
 */

declare(strict_types=1);

$pdo = getDbConnection();

$paymentFields = [
    'manual_adjusted_at' => 'TEXT',
    'manual_adjusted_by' => 'INTEGER',
];

foreach ($paymentFields as $field => $type) {
    try {
        $pdo->exec("ALTER TABLE payments ADD COLUMN {$field} {$type}");
        echo "Added column: {$field}\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'duplicate column name') === false) {
            echo "Warning: {$field} - {$e->getMessage()}\n";
        }
    }
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS payment_change_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payment_id INTEGER NOT NULL,
        changed_by INTEGER NOT NULL,
        change_reason TEXT NOT NULL,
        before_amount REAL,
        after_amount REAL,
        before_fx_rate_to_cny REAL,
        after_fx_rate_to_cny REAL,
        before_amount_cny REAL,
        after_amount_cny REAL,
        before_note TEXT,
        after_note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(payment_id) REFERENCES payments(id),
        FOREIGN KEY(changed_by) REFERENCES users(id)
    )'
);

$pdo->exec('CREATE INDEX IF NOT EXISTS idx_payment_change_logs_payment_id ON payment_change_logs(payment_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_payment_change_logs_created_at ON payment_change_logs(created_at)');

echo "Migration completed successfully.\n";
