<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/validate_endpoint.php';

$userId = (int) $_SESSION['user_id'];
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => '无效的订阅ID'], 400);
}

$stmt = $pdo->prepare('SELECT id FROM subscriptions WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $id, ':user_id' => $userId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => '订阅不存在或无权操作'], 404);
}

$status = $_POST['status'] ?? null;
$validStatuses = ['active', 'paused', 'cancelled'];

$isStatusOnlyUpdate =
    isset($status)
    && !isset($_POST['name'])
    && !isset($_POST['amount'])
    && !isset($_POST['currency_id'])
    && !isset($_POST['interval_value'])
    && !isset($_POST['interval_unit'])
    && !isset($_POST['start_date'])
    && !isset($_POST['category_id'])
    && !isset($_POST['payment_method_id'])
    && !isset($_POST['note'])
    && !isset($_POST['website_url'])
    && !isset($_POST['logo_url'])
    && !isset($_POST['logo_url_remote'])
    && !isset($_FILES['logo_file']);

if ($isStatusOnlyUpdate) {
    if (!in_array($status, $validStatuses, true)) {
        jsonResponse(['success' => false, 'message' => '无效的状态'], 400);
    }

    $stmt = $pdo->prepare('UPDATE subscriptions SET status = :status, updated_at = datetime("now") WHERE id = :id AND user_id = :user_id');
    $stmt->execute([
        ':status' => $status,
        ':id' => $id,
        ':user_id' => $userId,
    ]);

    jsonResponse([
        'success' => true,
        'message' => '订阅状态更新成功',
        'id' => $id,
    ]);
}

$name = trim((string) ($_POST['name'] ?? ''));
if ($name === '') {
    $stmt = $pdo->prepare('SELECT name FROM subscriptions WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':id' => $id, ':user_id' => $userId]);
    $existingName = $stmt->fetchColumn();
    if (!is_string($existingName) || trim($existingName) === '') {
        jsonResponse(['success' => false, 'message' => '服务名称不能为空'], 400);
    }
    $name = trim($existingName);
}

$amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
if ($amount === false || $amount < 0) {
    jsonResponse(['success' => false, 'message' => '金额无效'], 400);
}

$currencyId = isset($_POST['currency_id']) ? (int) $_POST['currency_id'] : null;
if ($currencyId === null || $currencyId <= 0) {
    jsonResponse(['success' => false, 'message' => '请选择币种'], 400);
}

$intervalValue = max(1, (int) ($_POST['interval_value'] ?? 1));
$intervalUnit = $_POST['interval_unit'] ?? 'month';
$validUnits = ['day', 'week', 'month', 'year'];
if (!in_array($intervalUnit, $validUnits, true)) {
    $intervalUnit = 'month';
}

$isLifetime = isset($_POST['is_lifetime']) && $_POST['is_lifetime'] == '1' ? 1 : 0;
$autoRenew = isset($_POST['auto_renew']) && $_POST['auto_renew'] == '1' ? 1 : 0;

$startDate = $_POST['start_date'] ?? null;
if ($startDate === null || $startDate === '') {
    $startDate = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    jsonResponse(['success' => false, 'message' => '开始日期格式无效'], 400);
}

$categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
$paymentMethodId = isset($_POST['payment_method_id']) && $_POST['payment_method_id'] !== '' ? (int) $_POST['payment_method_id'] : null;

$logoUrl = '';

// Handle file upload
if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../../../public/assets/images/uploads/logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileTmpPath = $_FILES['logo_file']['tmp_name'];
    $fileName = $_FILES['logo_file']['name'];
    $fileSize = $_FILES['logo_file']['size'];
    $fileType = $_FILES['logo_file']['type'];
    
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    
    if (in_array($fileExtension, $allowedExtensions)) {
        $newFileName = uniqid('logo_') . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $logoUrl = '/assets/images/uploads/logos/' . $newFileName;
        }
    }
}

// Check for uploaded logo first, then remote URL (only if no file uploaded)
if (empty($logoUrl)) {
    if (isset($_POST['logo_url']) && !empty(trim($_POST['logo_url']))) {
        $logoUrl = trim((string) $_POST['logo_url']);
    } elseif (isset($_POST['logo_url_remote']) && !empty(trim($_POST['logo_url_remote']))) {
        $logoUrl = trim((string) $_POST['logo_url_remote']);
    }
}

$websiteUrl = isset($_POST['website_url']) ? trim((string) $_POST['website_url']) : '';
$note = isset($_POST['note']) ? trim((string) $_POST['note']) : '';
$status = $_POST['status'] ?? 'active';
$validStatuses = ['active', 'paused', 'cancelled'];
if (!in_array($status, $validStatuses, true)) {
    $status = 'active';
}

$remindDaysBefore = max(0, (int) ($_POST['remind_days_before'] ?? 1));
$remindTime = isset($_POST['remind_time']) && $_POST['remind_time'] !== '' ? $_POST['remind_time'] : '09:00';

function getCurrencyRateToCny(PDO $pdo, int $currencyId): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(rate_to_cny, 1) FROM currencies WHERE id = :id');
    $stmt->execute([':id' => $currencyId]);
    $rate = (float) ($stmt->fetchColumn() ?: 1);
    return $rate > 0 ? $rate : 1.0;
}

function advanceBillingDate(DateTime $currentDate, DateTime $anchorDate, int $intervalValue, string $intervalUnit): DateTime
{
    $next = clone $currentDate;

    if ($intervalUnit === 'month') {
        $targetYear = (int) $next->format('Y');
        $targetMonth = (int) $next->format('n') + $intervalValue;

        while ($targetMonth > 12) {
            $targetMonth -= 12;
            $targetYear++;
        }

        $anchorDay = (int) $anchorDate->format('j');
        $daysInTarget = (int) date('t', mktime(0, 0, 0, $targetMonth, 1, $targetYear));
        $targetDay = min($anchorDay, $daysInTarget);
        $next->setDate($targetYear, $targetMonth, $targetDay);

        return $next;
    }

    if ($intervalUnit === 'year') {
        $targetYear = (int) $next->format('Y') + $intervalValue;
        $targetMonth = (int) $anchorDate->format('n');
        $anchorDay = (int) $anchorDate->format('j');
        $daysInTarget = (int) date('t', mktime(0, 0, 0, $targetMonth, 1, $targetYear));
        $targetDay = min($anchorDay, $daysInTarget);
        $next->setDate($targetYear, $targetMonth, $targetDay);

        return $next;
    }

    if ($intervalUnit === 'week') {
        $next->add(new DateInterval('P' . $intervalValue . 'W'));
        return $next;
    }

    $next->add(new DateInterval('P' . $intervalValue . 'D'));
    return $next;
}

function syncHistoricalPaymentsAndGetNextDate(PDO $pdo, int $subscriptionId, float $amount, int $currencyId, string $startDate, int $intervalValue, string $intervalUnit): string
{
    $today = new DateTime(date('Y-m-d'));
    $anchorDate = new DateTime($startDate);
    $currentDate = clone $anchorDate;
    $fxRateToCny = getCurrencyRateToCny($pdo, $currencyId);

    $insertStmt = $pdo->prepare("\n        INSERT INTO payments (subscription_id, amount, currency_id, paid_at, status, note, fx_rate_to_cny, amount_cny, fx_source, fx_locked_at)\n        VALUES (:subscription_id, :amount, :currency_id, :paid_at, 'success', 'Auto-generated history', :fx_rate_to_cny, :amount_cny, 'current_rate', :fx_locked_at)\n    ");

    $existsStmt = $pdo->prepare('SELECT id FROM payments WHERE subscription_id = :subscription_id AND DATE(paid_at) = :paid_at LIMIT 1');

    $loopGuard = 0;
    while ($currentDate <= $today && $loopGuard < 500) {
        $paidAt = $currentDate->format('Y-m-d');
        $existsStmt->execute([
            ':subscription_id' => $subscriptionId,
            ':paid_at' => $paidAt,
        ]);

        if (!$existsStmt->fetch()) {
            $insertStmt->execute([
                ':subscription_id' => $subscriptionId,
                ':amount' => $amount,
                ':currency_id' => $currencyId,
                ':paid_at' => $paidAt,
                ':fx_rate_to_cny' => $fxRateToCny,
                ':amount_cny' => $amount * $fxRateToCny,
                ':fx_locked_at' => $paidAt . ' 00:00:00',
            ]);
        }

        $currentDate = advanceBillingDate($currentDate, $anchorDate, $intervalValue, $intervalUnit);
        $loopGuard++;
    }

    return $currentDate->format('Y-m-d');
}

$pdo->beginTransaction();

try {
    if ($isLifetime) {
        $nextPaymentDate = null;
    } else {
        $nextPaymentDate = syncHistoricalPaymentsAndGetNextDate(
            $pdo,
            $id,
            (float) $amount,
            $currencyId,
            $startDate,
            $intervalValue,
            $intervalUnit
        );
    }

    $stmt = $pdo->prepare('UPDATE subscriptions SET name = :name, logo_url = :logo_url, amount = :amount, currency_id = :currency_id, interval_value = :interval_value, interval_unit = :interval_unit, is_lifetime = :is_lifetime, auto_renew = :auto_renew, start_date = :start_date, next_payment_date = :next_payment_date, category_id = :category_id, payment_method_id = :payment_method_id, note = :note, website_url = :website_url, status = :status, remind_days_before = :remind_days_before, remind_time = :remind_time, updated_at = datetime("now") WHERE id = :id AND user_id = :user_id');

    $stmt->execute([
        ':id' => $id,
        ':user_id' => $userId,
        ':name' => $name,
        ':logo_url' => $logoUrl ?: null,
        ':amount' => $amount,
        ':currency_id' => $currencyId,
        ':interval_value' => $intervalValue,
        ':interval_unit' => $intervalUnit,
        ':is_lifetime' => $isLifetime,
        ':auto_renew' => $autoRenew,
        ':start_date' => $startDate,
        ':next_payment_date' => $nextPaymentDate,
        ':category_id' => $categoryId,
        ':payment_method_id' => $paymentMethodId,
        ':note' => $note ?: null,
        ':website_url' => $websiteUrl ?: null,
        ':status' => $status,
        ':remind_days_before' => $remindDaysBefore,
        ':remind_time' => $remindTime,
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => '订阅更新成功',
        'id' => $id,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Update subscription failed: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => '订阅更新失败，请稍后重试',
    ], 500);
}
