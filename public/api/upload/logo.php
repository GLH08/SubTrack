<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/checksession.php';
require_once __DIR__ . '/../../../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verifyCsrfToken(is_string($csrfToken) ? $csrfToken : null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'message' => '请选择要上传的文件'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['logo'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '文件大小超出服务器限制',
        UPLOAD_ERR_FORM_SIZE => '文件大小超出表单限制',
        UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
        UPLOAD_ERR_NO_TMP_DIR => '服务器错误：缺少临时目录',
        UPLOAD_ERR_CANT_WRITE => '服务器错误：写入失败',
        UPLOAD_ERR_EXTENSION => '上传被扩展程序阻止',
    ];
    $errorMsg = $errorMessages[$file['error']] ?? '上传失败';
    echo json_encode(['success' => false, 'message' => $errorMsg], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
if ($finfo) {
    finfo_close($finfo);
}

if (!in_array($mimeType, $allowedTypes, true)) {
    echo json_encode(['success' => false, 'message' => '只支持 JPG、PNG、GIF 和 WebP 格式的图片'], JSON_UNESCAPED_UNICODE);
    exit;
}

$maxSize = 2 * 1024 * 1024;
if ((int) $file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => '文件大小不能超过 2MB'], JSON_UNESCAPED_UNICODE);
    exit;
}

$uploadDir = __DIR__ . '/../../assets/images/uploads/logos';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    echo json_encode(['success' => false, 'message' => '创建上传目录失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

$extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
if ($extension === '') {
    $extension = match ($mimeType) {
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg',
    };
}

$timestamp = time();
$randomPart = bin2hex(random_bytes(6));
$filename = "logo_{$timestamp}_{$randomPart}.{$extension}";
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
$webPath = '/assets/images/uploads/logos/' . $filename;

$success = is_uploaded_file($file['tmp_name'])
    ? move_uploaded_file($file['tmp_name'], $targetPath)
    : copy($file['tmp_name'], $targetPath);

if (!$success) {
    echo json_encode(['success' => false, 'message' => '文件保存失败，请检查目录权限'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => '上传成功',
    'data' => [
        'logo_url' => $webPath,
        'filename' => $filename,
    ],
], JSON_UNESCAPED_UNICODE);
