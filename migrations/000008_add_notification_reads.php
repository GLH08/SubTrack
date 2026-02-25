<?php

declare(strict_types=1);

$pdo = getDbConnection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS notification_reads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        subscription_id INTEGER NOT NULL,
        due_date TEXT NOT NULL,
        read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(subscription_id) REFERENCES subscriptions(id),
        UNIQUE(user_id, subscription_id, due_date)
    )'
);

$pdo->exec('CREATE INDEX IF NOT EXISTS idx_notification_reads_user_id ON notification_reads(user_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_notification_reads_user_sub_due ON notification_reads(user_id, subscription_id, due_date)');

echo "Migration completed successfully.\n";
