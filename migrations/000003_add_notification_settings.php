<?php
/**
 * Migration: Add notification settings fields
 * Adds Telegram and Email SMTP configuration fields to settings table
 */

declare(strict_types=1);

$pdo = getDbConnection();

// Add notification fields to settings if they don't exist
$fields = [
    'telegram_token' => 'TEXT',
    'telegram_chat_id' => 'TEXT',
    'email_host' => 'TEXT',
    'email_port' => 'INTEGER DEFAULT 587',
    'email_username' => 'TEXT',
    'email_password' => 'TEXT',
    'email_from' => 'TEXT',
    'email_to' => 'TEXT',
    'remind_sent_at' => 'DATETIME',
];

foreach ($fields as $field => $type) {
    try {
        $pdo->exec("ALTER TABLE settings ADD COLUMN {$field} {$type}");
        echo "Added column: {$field}\n";
    } catch (PDOException $e) {
        // Column may already exist
        if (strpos($e->getMessage(), 'duplicate column name') === false) {
            echo "Warning: {$field} - {$e->getMessage()}\n";
        }
    }
}

// Add remind_sent_at to subscriptions table
try {
    $pdo->exec("ALTER TABLE subscriptions ADD COLUMN remind_sent_at DATETIME");
    echo "Added column: remind_sent_at to subscriptions\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column name') === false) {
        echo "Warning: remind_sent_at - {$e->getMessage()}\n";
    }
}

echo "Migration completed successfully.\n";
