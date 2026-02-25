<?php

declare(strict_types=1);

$pdo = getDbConnection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT,
        password_hash TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        color TEXT DEFAULT "#6366f1",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS currencies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        symbol TEXT NOT NULL,
        name TEXT NOT NULL,
        rate_to_cny REAL DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS payment_methods (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        enabled INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        logo_url TEXT,
        amount REAL NOT NULL,
        currency_id INTEGER NOT NULL,
        interval_value INTEGER NOT NULL DEFAULT 1,
        interval_unit TEXT NOT NULL DEFAULT "month",
        is_lifetime INTEGER NOT NULL DEFAULT 0,
        auto_renew INTEGER NOT NULL DEFAULT 1,
        start_date TEXT,
        next_payment_date TEXT,
        category_id INTEGER,
        payment_method_id INTEGER,
        note TEXT,
        website_url TEXT,
        status TEXT NOT NULL DEFAULT "active",
        remind_days_before INTEGER DEFAULT 1,
        remind_time TEXT DEFAULT "09:00",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(currency_id) REFERENCES currencies(id),
        FOREIGN KEY(category_id) REFERENCES categories(id),
        FOREIGN KEY(payment_method_id) REFERENCES payment_methods(id)
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        subscription_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        currency_id INTEGER NOT NULL,
        paid_at TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "success",
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(subscription_id) REFERENCES subscriptions(id),
        FOREIGN KEY(currency_id) REFERENCES currencies(id)
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        default_currency_id INTEGER,
        timezone TEXT DEFAULT "Asia/Shanghai",
        notify_email INTEGER NOT NULL DEFAULT 0,
        notify_telegram INTEGER NOT NULL DEFAULT 0,
        fx_provider TEXT,
        fx_api_key TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(default_currency_id) REFERENCES currencies(id)
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS login_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        token_hash TEXT NOT NULL,
        expires_at TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        token TEXT NOT NULL,
        expires_at TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(email)
    )'
);

// Global settings table for system-wide configuration
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS global_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key_name TEXT NOT NULL UNIQUE,
        value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

// Initialize default global settings
$pdo->exec("INSERT OR IGNORE INTO global_settings (key_name, value) VALUES ('exchange_rate_last_update', datetime('now'))");
