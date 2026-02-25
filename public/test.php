<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/checksession.php';

if (getenv('APP_DEBUG') !== '1' || !isLoggedIn()) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "test";
