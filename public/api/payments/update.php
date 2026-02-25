<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/validate_endpoint.php';

$pdo = getDbConnection();
$userId = (int) $_SESSION['user_id'];

$paymentId = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;
if ($paymentId <= 0) {
    jsonResponse(['success' => false, 'message' => '无效的扣款记录ID'], 400);
}

$changeReason = trim((string) ($_POST['change_reason'] ?? ''));
if ($changeReason === '') {
    jsonResponse(['success' => false, 'message' => '请填写变更原因'], 400);
}

$stmt = $pdo->prepare('SELECT p.*, s.user_id FROM payments p INNER JOIN subscriptions s ON s.id = p.subscription_id WHERE p.id = :payment_id LIMIT 1');
$stmt->execute([':payment_id' => $paymentId]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment || (int) $payment['user_id'] !== $userId) {
    jsonResponse(['success' => false, 'message' => '扣款记录不存在或无权操作'], 404);
}

$hasAmount = array_key_exists('amount', $_POST);
$hasFxRate = array_key_exists('fx_rate_to_cny', $_POST);
$hasNote = array_key_exists('note', $_POST);

if (!$hasAmount && !$hasFxRate && !$hasNote) {
    jsonResponse(['success' => false, 'message' => '没有可更新的字段'], 400);
}

$newAmount = (float) $payment['amount'];
if ($hasAmount) {
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    if ($amount === false || $amount < 0) {
        jsonResponse(['success' => false, 'message' => '金额无效'], 400);
    }
    $newAmount = (float) $amount;
}

$currentFxRate = isset($payment['fx_rate_to_cny']) ? (float) $payment['fx_rate_to_cny'] : 1.0;
if ($currentFxRate <= 0) {
    $currentFxRate = 1.0;
}

$newFxRate = $currentFxRate;
if ($hasFxRate) {
    $fxRate = filter_var($_POST['fx_rate_to_cny'], FILTER_VALIDATE_FLOAT);
    if ($fxRate === false || (float) $fxRate <= 0) {
        jsonResponse(['success' => false, 'message' => '汇率无效'], 400);
    }
    $newFxRate = (float) $fxRate;
}

$newNote = $payment['note'] ?? null;
if ($hasNote) {
    $note = trim((string) $_POST['note']);
    $newNote = $note === '' ? null : $note;
}

$newAmountCny = $newAmount * $newFxRate;
$beforeAmount = (float) $payment['amount'];
$beforeFxRate = $currentFxRate;
$beforeAmountCny = isset($payment['amount_cny']) ? (float) $payment['amount_cny'] : ($beforeAmount * $beforeFxRate);
$beforeNote = $payment['note'] ?? null;

$pdo->beginTransaction();

try {
    $updateStmt = $pdo->prepare('UPDATE payments SET amount = :amount, note = :note, fx_rate_to_cny = :fx_rate_to_cny, amount_cny = :amount_cny, fx_source = :fx_source, fx_locked_at = :fx_locked_at, manual_adjusted_at = datetime("now"), manual_adjusted_by = :manual_adjusted_by WHERE id = :id');
    $updateStmt->execute([
        ':amount' => $newAmount,
        ':note' => $newNote,
        ':fx_rate_to_cny' => $newFxRate,
        ':amount_cny' => $newAmountCny,
        ':fx_source' => 'manual_adjustment',
        ':fx_locked_at' => date('Y-m-d H:i:s'),
        ':manual_adjusted_by' => $userId,
        ':id' => $paymentId,
    ]);

    $logStmt = $pdo->prepare('INSERT INTO payment_change_logs (payment_id, changed_by, change_reason, before_amount, after_amount, before_fx_rate_to_cny, after_fx_rate_to_cny, before_amount_cny, after_amount_cny, before_note, after_note) VALUES (:payment_id, :changed_by, :change_reason, :before_amount, :after_amount, :before_fx_rate_to_cny, :after_fx_rate_to_cny, :before_amount_cny, :after_amount_cny, :before_note, :after_note)');
    $logStmt->execute([
        ':payment_id' => $paymentId,
        ':changed_by' => $userId,
        ':change_reason' => $changeReason,
        ':before_amount' => $beforeAmount,
        ':after_amount' => $newAmount,
        ':before_fx_rate_to_cny' => $beforeFxRate,
        ':after_fx_rate_to_cny' => $newFxRate,
        ':before_amount_cny' => $beforeAmountCny,
        ':after_amount_cny' => $newAmountCny,
        ':before_note' => $beforeNote,
        ':after_note' => $newNote,
    ]);

    $pdo->commit();

    $freshStmt = $pdo->prepare('SELECT p.*, c.symbol AS currency_symbol FROM payments p LEFT JOIN currencies c ON c.id = p.currency_id WHERE p.id = :id LIMIT 1');
    $freshStmt->execute([':id' => $paymentId]);
    $updatedPayment = $freshStmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'message' => '扣款记录已更新',
        'payment' => $updatedPayment,
        'log_id' => (int) $pdo->lastInsertId(),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Update payment failed: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => '扣款记录更新失败，请稍后重试',
    ], 500);
}
