<?php

declare(strict_types=1);

$pdo = getDbConnection();

$seedPassword = getenv('SUBTRACK_ADMIN_PASSWORD');
if (!is_string($seedPassword) || trim($seedPassword) === '') {
    $seedPassword = 'change-me-' . bin2hex(random_bytes(6));
    error_log('SUBTRACK_ADMIN_PASSWORD is not set. Generated bootstrap admin password: ' . $seedPassword . '. Please change it immediately after first login.');
}

$passwordHash = password_hash($seedPassword, PASSWORD_ARGON2ID);

$insertUser = $pdo->prepare('INSERT OR IGNORE INTO users (id, username, email, password_hash) VALUES (1, :username, :email, :password_hash)');
$insertUser->execute([
    ':username' => 'admin',
    ':email' => 'admin@subtrack.local',
    ':password_hash' => $passwordHash,
]);

$currencies = [
    ['CNY', '¥', 'Chinese Yuan', 1],
    ['USD', '$', 'US Dollar', 7.2],
    ['HKD', 'HK$', 'Hong Kong Dollar', 0.92],
    ['EUR', '€', 'Euro', 7.8],
];

$insertCurrency = $pdo->prepare('INSERT OR IGNORE INTO currencies (code, symbol, name, rate_to_cny) VALUES (:code, :symbol, :name, :rate)');
foreach ($currencies as [$code, $symbol, $name, $rate]) {
    $insertCurrency->execute([
        ':code' => $code,
        ':symbol' => $symbol,
        ':name' => $name,
        ':rate' => $rate,
    ]);
}

$categories = ['SaaS服务', '娱乐影音', '生产力工具', '服务器', 'AI工具'];
$insertCategory = $pdo->prepare('INSERT OR IGNORE INTO categories (name) VALUES (:name)');
foreach ($categories as $category) {
    $insertCategory->execute([':name' => $category]);
}

$methods = ['支付宝', '微信支付', '信用卡', 'PayPal'];
$insertMethod = $pdo->prepare('INSERT OR IGNORE INTO payment_methods (name, enabled) VALUES (:name, 1)');
foreach ($methods as $method) {
    $insertMethod->execute([':name' => $method]);
}

$defaultCurrencyId = (int) $pdo->query("SELECT id FROM currencies WHERE code = 'CNY' LIMIT 1")->fetchColumn();
$insertSettings = $pdo->prepare('INSERT OR IGNORE INTO settings (user_id, default_currency_id, timezone, notify_email, notify_telegram) VALUES (1, :default_currency_id, :timezone, 0, 0)');
$insertSettings->execute([
    ':default_currency_id' => $defaultCurrencyId > 0 ? $defaultCurrencyId : null,
    ':timezone' => 'Asia/Shanghai',
]);
