<?php
require_once __DIR__ . '/../../includes/connect.php';
require_once __DIR__ . '/../../includes/checksession.php';

if (!isLoggedIn() || getenv('APP_DEBUG') !== '1') {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$pdo = getDbConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get user's preferred display currency (default to CNY)
$stmt = $pdo->prepare("SELECT default_currency_id FROM settings WHERE user_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$preferredCurrencyId = $stmt->fetchColumn();

// Get currency info
if ($preferredCurrencyId) {
    $stmt = $pdo->prepare("SELECT code, symbol, rate_to_cny FROM currencies WHERE id = :id");
    $stmt->execute([':id' => $preferredCurrencyId]);
    $displayCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query("SELECT code, symbol, rate_to_cny FROM currencies WHERE code = 'CNY'");
    $displayCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$displayCurrency) {
    $displayCurrency = ['code' => 'CNY', 'symbol' => '¥', 'rate_to_cny' => 1];
}

$displayRate = (float) ($displayCurrency['rate_to_cny'] ?? 1);

echo "Display Currency: {$displayCurrency['code']}\n";
echo "Display Rate: {$displayRate}\n\n";

$monthlyHistory = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN p.amount_cny IS NOT NULL AND p.amount_cny > 0 THEN p.amount_cny
                ELSE p.amount * c.rate_to_cny
            END
        ), 0) as total
        FROM payments p
        JOIN subscriptions s ON p.subscription_id = s.id
        LEFT JOIN currencies c ON p.currency_id = c.id
        WHERE s.user_id = :user_id
        AND strftime('%Y-%m', p.paid_at) = :month
        AND p.status = 'success'
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':month' => $month
    ]);
    $totalInCny = $stmt->fetchColumn();

    $displayAmount = $totalInCny / ($displayRate ?: 1);

    $monthlyHistory[] = [
        'month' => date('n月', strtotime($month)),
        'raw_month' => $month,
        'amount' => $displayAmount,
        'amount_raw_cny' => $totalInCny
    ];

    echo sprintf("%-8s %-6s Raw CNY: %-10s Display: %-10s\n",
        $month,
        date('n月', strtotime($month)),
        number_format($totalInCny, 2),
        number_format($displayAmount, 2)
    );
}

$maxAmount = max(array_column($monthlyHistory, 'amount'));
if ($maxAmount == 0) $maxAmount = 1;

echo "\nmaxAmount: {$maxAmount}\n\n";
echo "Height Percentages:\n";
foreach ($monthlyHistory as $index => $data) {
    $heightPercent = ($data['amount'] / $maxAmount) * 100;
    $actualHeight = max(5, $heightPercent);
    echo sprintf("%-8s: %.1f%% (actual: %.1f%%)\n", $data['raw_month'], $heightPercent, $actualHeight);
}
