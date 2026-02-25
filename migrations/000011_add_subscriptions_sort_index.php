<?php

declare(strict_types=1);

$pdo = getDbConnection();

$pdo->exec('CREATE INDEX IF NOT EXISTS idx_subscriptions_user_next_payment_id ON subscriptions(user_id, next_payment_date, id)');

echo "Migration completed successfully.\n";
