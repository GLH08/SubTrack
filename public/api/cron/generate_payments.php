<?php
require_once __DIR__ . '/../../../includes/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$cronToken = getenv('SUBTRACK_CRON_TOKEN');
$requestToken = trim((string) ($_SERVER['HTTP_X_CRON_TOKEN'] ?? ''));
if (!is_string($cronToken) || $cronToken === '' || !hash_equals($cronToken, $requestToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$pdo = getDbConnection();
$today = date('Y-m-d');
$log = [];

try {
    $pdo->beginTransaction();

    // 1. Get ALL active/paused subscriptions to check for missing history
    $stmt = $pdo->prepare("SELECT s.*, COALESCE(c.rate_to_cny, 1) AS currency_rate_to_cny FROM subscriptions s LEFT JOIN currencies c ON s.currency_id = c.id WHERE s.status IN ('active', 'paused')");
    $stmt->execute();
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    
    foreach ($subs as $sub) {
        $startDate = new DateTime($sub['start_date']);
        $todayDt = new DateTime($today);
        
        $intervalVal = $sub['interval_value'];
        $intervalUnit = $sub['interval_unit'];
        
        // Calculate interval string
        $intervalStr = "P{$intervalVal}";
        if ($intervalUnit === 'day') $intervalStr .= 'D';
        elseif ($intervalUnit === 'week') $intervalStr .= 'W'; // P1W is valid
        elseif ($intervalUnit === 'month') $intervalStr .= 'M';
        elseif ($intervalUnit === 'year') $intervalStr .= 'Y';
        else $intervalStr .= 'M'; // Fallback
        
        $interval = new DateInterval($intervalStr);

        // Iterate from start_date until today
        $currentDate = clone $startDate;
        
        // We need to keep checking until we reach future
        // Also track the last valid payment date to update next_payment_date
        $nextDate = clone $startDate;

        // Safety limit
        $loops = 0;
        
        while ($currentDate <= $todayDt && $loops < 500) {
            $dateStr = $currentDate->format('Y-m-d');
            
            // Check if payment exists for this sub on this date (approx)
            // We use a date range check because exact time might vary, but for simple day-based, Y-m-d is fine if we store Y-m-d
            // The table likely stores 'paid_at' as DATE or DATETIME. Let's assume DATE comparison.
            $check = $pdo->prepare("SELECT id FROM payments WHERE subscription_id = :sid AND DATE(paid_at) = :pdate");
            $check->execute([':sid' => $sub['id'], ':pdate' => $dateStr]);
            
            if (!$check->fetch()) {
                // Not found, insert history
                $insert = $pdo->prepare("
                    INSERT INTO payments (subscription_id, amount, currency_id, paid_at, status, note, fx_rate_to_cny, amount_cny, fx_source, fx_locked_at)
                    VALUES (:sub_id, :amount, :curr_id, :paid_at, 'success', 'Auto-generated history', :fx_rate_to_cny, :amount_cny, 'current_rate', :fx_locked_at)
                ");
                $fxRateToCny = (float) ($sub['currency_rate_to_cny'] ?? 1);
                if ($fxRateToCny <= 0) {
                    $fxRateToCny = 1;
                }
                $insert->execute([
                    ':sub_id' => $sub['id'],
                    ':amount' => $sub['amount'],
                    ':curr_id' => $sub['currency_id'],
                    ':paid_at' => $dateStr,
                    ':fx_rate_to_cny' => $fxRateToCny,
                    ':amount_cny' => (float) $sub['amount'] * $fxRateToCny,
                    ':fx_locked_at' => $dateStr . ' 00:00:00'
                ]);
                $count++;
            }
            
            // Advance
            // Use custom month logic if 'month' to avoid overflow issues (e.g. jan 31 -> mar 3)
            // But for simple backfill, standard add is usually acceptable unless user complains about specific dates.
            // Given I just fixed calendar to be smart, I should ideally be smart here too.
            // But DateInterval is standard. Let's use standard for now as it's backend logic.
            // Wait, if I use standard add for 1 month starting Jan 31, I get Mar 3. Then next is Apr 3.
            // This DRIFTS the payment date. This is BAD.
            // I must use the same "Snap to End" logic or "Original Day" logic.
            
            if ($intervalUnit === 'month' || $intervalUnit === 'year') {
                 $targetYear = (int)$currentDate->format('Y');
                 $targetMonth = (int)$currentDate->format('n');
                 $originalDay = (int)$startDate->format('j');
                 
                 if ($intervalUnit === 'month') {
                     $targetMonth += $intervalVal;
                 } else {
                     $targetYear += $intervalVal;
                 }
                 
                 // Normalize month/year
                 while ($targetMonth > 12) {
                     $targetMonth -= 12;
                     $targetYear++;
                 }
                 
                 $daysInTarget = (int)date('t', mktime(0,0,0, $targetMonth, 1, $targetYear));
                 $targetDay = min($originalDay, $daysInTarget);
                 
                 $currentDate->setDate($targetYear, $targetMonth, $targetDay);
            } else {
                 $currentDate->add($interval);
            }
            
            $loops++;
        }
        
        // $currentDate is now the first date > today aka Next Payment Date
        // Update subscription
        $update = $pdo->prepare("UPDATE subscriptions SET next_payment_date = :npd WHERE id = :id");
        $update->execute([':npd' => $currentDate->format('Y-m-d'), ':id' => $sub['id']]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'processed_count' => $count, 'log' => []]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Payment generation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
