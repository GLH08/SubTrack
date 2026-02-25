<?php

declare(strict_types=1);

$pdo = getDbConnection();

$pdo->exec('CREATE INDEX IF NOT EXISTS idx_subscriptions_user_status_next_payment ON subscriptions(user_id, status, next_payment_date)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_payments_subscription_paid_status ON payments(subscription_id, paid_at, status)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_subscriptions_user_status_created ON subscriptions(user_id, status, created_at)');

echo "Migration completed successfully.\n";
