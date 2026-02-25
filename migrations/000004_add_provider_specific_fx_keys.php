<?php
/**
 * Migration: Add provider-specific FX API key fields
 * Stores separate API keys per provider to avoid overwriting when switching providers
 */

declare(strict_types=1);

$pdo = getDbConnection();

$fields = [
    'fx_api_key_apilayer' => 'TEXT',
    'fx_api_key_fixer' => 'TEXT',
    'fx_api_key_exchangerate' => 'TEXT',
];

foreach ($fields as $field => $type) {
    try {
        $pdo->exec("ALTER TABLE settings ADD COLUMN {$field} {$type}");
        echo "Added column: {$field}\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'duplicate column name') === false) {
            echo "Warning: {$field} - {$e->getMessage()}\n";
        }
    }
}

// Backfill legacy single API key into provider-specific columns
$pdo->exec("UPDATE settings SET fx_api_key_apilayer = fx_api_key WHERE fx_provider = 'apilayer.com' AND (fx_api_key_apilayer IS NULL OR fx_api_key_apilayer = '') AND fx_api_key IS NOT NULL AND fx_api_key != ''");
$pdo->exec("UPDATE settings SET fx_api_key_fixer = fx_api_key WHERE fx_provider = 'fixer.io' AND (fx_api_key_fixer IS NULL OR fx_api_key_fixer = '') AND fx_api_key IS NOT NULL AND fx_api_key != ''");
$pdo->exec("UPDATE settings SET fx_api_key_exchangerate = fx_api_key WHERE fx_provider = 'exchangerate-api.com' AND (fx_api_key_exchangerate IS NULL OR fx_api_key_exchangerate = '') AND fx_api_key IS NOT NULL AND fx_api_key != ''");

echo "Migration completed successfully.\n";
