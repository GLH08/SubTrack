<?php

declare(strict_types=1);

$pdo = getDbConnection();

$columns = [];
$stmt = $pdo->query("PRAGMA table_info(password_resets)");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $columns[] = $column['name'];
}

if (!in_array('selector', $columns, true) || !in_array('token_hash', $columns, true)) {
    $pdo->exec('DROP TABLE IF EXISTS password_resets_new');

    $pdo->exec(
        'CREATE TABLE password_resets_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            selector TEXT NOT NULL,
            token_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(email)
        )'
    );

    if (in_array('token', $columns, true)) {
        $legacyRows = $pdo->query('SELECT id, email, token, expires_at, created_at FROM password_resets')->fetchAll(PDO::FETCH_ASSOC);
        $insertStmt = $pdo->prepare(
            'INSERT OR REPLACE INTO password_resets_new (id, email, selector, token_hash, expires_at, created_at)
             VALUES (:id, :email, :selector, :token_hash, :expires_at, :created_at)'
        );

        foreach ($legacyRows as $row) {
            $legacyToken = (string) ($row['token'] ?? '');
            if ($legacyToken === '') {
                continue;
            }

            $selector = substr($legacyToken, 0, 16);
            if ($selector === '' || strlen($selector) < 8) {
                $selector = bin2hex(random_bytes(8));
            }

            $insertStmt->execute([
                ':id' => (int) $row['id'],
                ':email' => (string) $row['email'],
                ':selector' => $selector,
                ':token_hash' => hash('sha256', $legacyToken),
                ':expires_at' => (string) $row['expires_at'],
                ':created_at' => (string) ($row['created_at'] ?? date('Y-m-d H:i:s')),
            ]);
        }
    }

    $pdo->exec('DROP TABLE password_resets');
    $pdo->exec('ALTER TABLE password_resets_new RENAME TO password_resets');
}
