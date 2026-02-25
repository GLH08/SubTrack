<?php

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/checksession.php';
require_once __DIR__ . '/../../../includes/csrf.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Get API settings
    $stmt = $pdo->prepare("SELECT fx_provider, fx_api_key, fx_api_key_apilayer, fx_api_key_fixer, fx_api_key_exchangerate FROM settings WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    $provider = $settings['fx_provider'] ?? 'exchangerate-api.com';
    $providerApiKeys = [
        'apilayer.com' => (string) ($settings['fx_api_key_apilayer'] ?? ''),
        'fixer.io' => (string) ($settings['fx_api_key_fixer'] ?? ''),
        'exchangerate-api.com' => (string) ($settings['fx_api_key_exchangerate'] ?? ''),
    ];
    $apiKey = $providerApiKeys[$provider] ?? (string) ($settings['fx_api_key'] ?? '');

    // exchangerate-api.com doesn't require API key for basic usage
    if ($provider !== 'exchangerate-api.com' && empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => '请先在 API 设置中配置 API Key']);
        exit;
    }

    // Get all currencies except CNY (base currency)
    $stmt = $pdo->query("SELECT id, code FROM currencies WHERE code != 'CNY'");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($currencies)) {
        echo json_encode(['success' => false, 'message' => '没有需要更新的货币']);
        exit;
    }

    $updatedCount = 0;
    $errors = [];

    // Fetch exchange rates from API
    if ($provider === 'exchangerate-api.com') {
        // exchangerate-api.com - Free API with CNY as base currency
        // Free tier endpoint (no API key required)
        $url = 'https://open.er-api.com/v6/latest/CNY';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("API request failed. HTTP Code: $httpCode, Response: $response");
            echo json_encode(['success' => false, 'message' => 'API 请求失败，状态码 ' . $httpCode . '。请检查网络连接。']);
            exit;
        }

        if ($curlError) {
            error_log("CURL error: $curlError");
            echo json_encode(['success' => false, 'message' => '网络请求失败: ' . $curlError]);
            exit;
        }

        $data = json_decode($response, true);

        if (!$data || $data['result'] !== 'success') {
            $errorMsg = $data['error-type'] ?? 'API 返回数据格式错误';
            error_log("API error: " . json_encode($data));
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }

        $rates = $data['rates'] ?? [];

        // Update each currency (convert to inverse: 1 USD = X CNY instead of 1 CNY = X USD)
        foreach ($currencies as $currency) {
            $code = $currency['code'];

            if (isset($rates[$code])) {
                // API returns: 1 CNY = 0.14 USD
                // We want: 1 USD = 7.14 CNY
                // So we calculate: 1 / rate
                $apiRate = floatval($rates[$code]);

                // Avoid division by zero
                if ($apiRate == 0) {
                    $errors[] = $code . ' (汇率为0)';
                    continue;
                }

                $rate = 1 / $apiRate; // Inverse the rate

                // Update rate in database
                $stmt = $pdo->prepare("UPDATE currencies SET rate_to_cny = :rate WHERE id = :id");
                $stmt->execute(['rate' => $rate, 'id' => $currency['id']]);

                $updatedCount++;
            } else {
                $errors[] = $code . ' (未找到汇率)';
            }
        }
    } elseif ($provider === 'apilayer.com') {
        // apilayer.com Fixer API endpoint (base is EUR for free plan)
        $url = 'https://api.apilayer.com/fixer/latest?symbols=' . implode(',', array_column($currencies, 'code')) . ',CNY';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("API request failed. HTTP Code: $httpCode, Response: $response");
            echo json_encode(['success' => false, 'message' => 'API 请求失败，状态码 ' . $httpCode . '。请检查 API Key 是否有效。']);
            exit;
        }

        if ($curlError) {
            error_log("CURL error: $curlError");
            echo json_encode(['success' => false, 'message' => '网络请求失败: ' . $curlError]);
            exit;
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['success']) || !$data['success']) {
            $errorMsg = isset($data['error']) ? $data['error']['info'] ?? $data['error']['message'] ?? 'API 返回错误' : 'API 返回数据格式错误';
            error_log("API error: " . json_encode($data));
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }

        $rates = $data['rates'] ?? [];

        // Get CNY rate (base is EUR)
        $cnyRate = $rates['CNY'] ?? null;

        if (!$cnyRate) {
            echo json_encode(['success' => false, 'message' => '无法获取 CNY 汇率']);
            exit;
        }

        // Update each currency (convert from EUR-based to CNY-based)
        foreach ($currencies as $currency) {
            $code = $currency['code'];

            if (isset($rates[$code])) {
                // API returns EUR-based rates: 1 EUR = X USD, 1 EUR = Y CNY
                // We want: 1 USD = Z CNY
                // Calculate: Z = Y / X (CNY per EUR / USD per EUR = CNY per USD)
                $rateToEur = floatval($rates[$code]);
                $rate = $cnyRate / $rateToEur; // This gives us: 1 USD = X CNY

                // Update rate in database
                $stmt = $pdo->prepare("UPDATE currencies SET rate_to_cny = :rate WHERE id = :id");
                $stmt->execute(['rate' => $rate, 'id' => $currency['id']]);

                $updatedCount++;
            } else {
                $errors[] = $code . ' (未找到汇率)';
            }
        }
    } elseif ($provider === 'fixer.io') {
        // fixer.io API endpoint (base is EUR for free plan)
        $symbolsList = implode(',', array_column($currencies, 'code')) . ',CNY';
        $url = 'https://api.fixer.io/latest?symbols=' . $symbolsList . '&access_key=' . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("API request failed. HTTP Code: $httpCode, Response: $response");
            echo json_encode(['success' => false, 'message' => 'API 请求失败，状态码 ' . $httpCode]);
            exit;
        }

        if ($curlError) {
            error_log("CURL error: $curlError");
            echo json_encode(['success' => false, 'message' => '网络请求失败: ' . $curlError]);
            exit;
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['success']) || !$data['success']) {
            $errorMsg = $data['error']['info'] ?? 'API 返回数据格式错误';
            error_log("API error: " . json_encode($data));
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }

        $rates = $data['rates'] ?? [];

        // Get CNY rate (base is EUR)
        $cnyRate = $rates['CNY'] ?? null;

        if (!$cnyRate) {
            echo json_encode(['success' => false, 'message' => '无法获取 CNY 汇率']);
            exit;
        }

        // Update each currency (convert from EUR-based to CNY-based)
        foreach ($currencies as $currency) {
            $code = $currency['code'];

            if (isset($rates[$code])) {
                // API returns EUR-based rates: 1 EUR = X USD, 1 EUR = Y CNY
                // We want: 1 USD = Z CNY
                // Calculate: Z = Y / X (CNY per EUR / USD per EUR = CNY per USD)
                $rateToEur = floatval($rates[$code]);
                $rate = $cnyRate / $rateToEur; // This gives us: 1 USD = X CNY

                // Update rate in database
                $stmt = $pdo->prepare("UPDATE currencies SET rate_to_cny = :rate WHERE id = :id");
                $stmt->execute(['rate' => $rate, 'id' => $currency['id']]);

                $updatedCount++;
            } else {
                $errors[] = $code . ' (未找到汇率)';
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => '不支持的 API 提供商']);
        exit;
    }

    // Update the last update timestamp in global_settings
    $currentTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE global_settings SET value = :time, updated_at = :time WHERE key_name = 'exchange_rate_last_update'");
    $stmt->execute(['time' => $currentTime]);

    // If no rows were updated, insert the record
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("INSERT INTO global_settings (key_name, value, created_at, updated_at) VALUES ('exchange_rate_last_update', :time, :time, :time)");
        $stmt->execute(['time' => $currentTime]);
    }

    $message = "成功更新 {$updatedCount} 个货币的汇率";
    if (!empty($errors)) {
        $message .= '，部分货币更新失败: ' . implode(', ', $errors);
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'updated_count' => $updatedCount,
            'last_update' => date('Y-m-d H:i', strtotime($currentTime)),
            'errors' => $errors
        ]
    ]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
} catch (Exception $e) {
    error_log("Update rates error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '更新失败: ' . $e->getMessage()]);
}
