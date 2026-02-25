#!/usr/bin/env php
<?php
/**
 * SubTrack CLI - Cron Job Runner
 *
 * Usage:
 *   php cli/cron.php              # Run all scheduled tasks
 *   php cli/cron.php exchange     # Update exchange rates only
 *   php cli/cron.php reminders     # Send due reminders only
 *   php cli/cron.php all           # Run all tasks
 *
 * Schedule in crontab:
 *   # Run every hour
 *   0 * * * * cd /path/to/subtrack && php cli/cron.php all
 *
 *   # Or run every 6 hours
 *   # cron expression: minute=0, hour-step=6
 *   0 [every-6-hours] * * * cd /path/to/subtrack && php cli/cron.php exchange
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/NotificationService.php';

class CronRunner
{
    private bool $verbose = false;
    private int $successCount = 0;
    private int $errorCount = 0;

    public function __construct(bool $verbose = false)
    {
        $this->verbose = $verbose;
    }

    public function run(string $task = 'all'): void
    {
        $startTime = microtime(true);
        $this->log("========================================");
        $this->log("SubTrack Cron Job Runner");
        $this->log("Task: {$task}");
        $this->log("Time: " . date('Y-m-d H:i:s'));
        $this->log("========================================\n");

        switch ($task) {
            case 'exchange':
            case 'exchange-rates':
                $this->updateExchangeRates();
                break;

            case 'reminders':
            case 'due-reminders':
                $this->sendDueReminders();
                break;

            case 'all':
            case 'everything':
                $this->updateExchangeRates();
                $this->sendDueReminders();
                break;

            default:
                $this->log("Unknown task: {$task}");
                $this->log("Available tasks: exchange, reminders, all");
                exit(1);
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->log("\n========================================");
        $this->log("Completed in {$duration}s");
        $this->log("Success: {$this->successCount}, Errors: {$this->errorCount}");
        $this->log("========================================");

        exit($this->errorCount > 0 ? 1 : 0);
    }

    private function updateExchangeRates(): void
    {
        $this->log("\n[1/2] Updating exchange rates...");

        try {
            $apiUrl = "https://api.frankfurter.app/latest?from=USD";
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 30,
                    'user_agent' => 'SubTrack-Cron/1.0'
                ]
            ]);

            $response = @file_get_contents($apiUrl, false, $context);

            if ($response === false) {
                // Try fallback API
                $fallbackUrl = "https://open.er-api.com/v6/latest/USD";
                $response = @file_get_contents($fallbackUrl, false, $context);
            }

            if ($response === false) {
                throw new Exception("Failed to fetch exchange rates from API");
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_OK || !isset($data['rates'])) {
                throw new Exception("Invalid response from exchange rate API");
            }

            $pdo = getDbConnection();
            $pdo->beginTransaction();

            $stmt = $pdo->query("SELECT id, code FROM currencies WHERE code != 'USD'");
            $currencies = $stmt->fetchAll();

            $updatedCount = 0;
            foreach ($currencies as $currency) {
                if (isset($data['rates'][$currency['code']])) {
                    $rate = (float) $data['rates'][$currency['code']];
                    $updateStmt = $pdo->prepare("
                        UPDATE currencies SET rate_to_cny = :rate, updated_at = datetime('now')
                        WHERE id = :id
                    ");
                    $updateStmt->execute([':rate' => $rate, ':id' => $currency['id']]);
                    $updatedCount++;
                }
            }

            // Update last update time
            $pdo->exec("
                UPDATE global_settings SET value = datetime('now'), updated_at = datetime('now')
                WHERE key_name = 'exchange_rate_last_update'
            ");

            $pdo->commit();

            $this->log("  ✓ Updated {$updatedCount} exchange rates");
            $this->successCount++;

        } catch (Exception $e) {
            $this->log("  ✗ Error updating exchange rates: " . $e->getMessage());
            $this->errorCount++;

            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    private function sendDueReminders(): void
    {
        $this->log("\n[2/2] Checking for subscription due reminders...");

        try {
            $sentCount = sendDueReminders();
            $this->log("  ✓ Sent {$sentCount} reminder notifications");
            $this->successCount++;

        } catch (Exception $e) {
            $this->log("  ✗ Error sending reminders: " . $e->getMessage());
            $this->errorCount++;
        }
    }

    private function log(string $message): void
    {
        if ($this->verbose) {
            echo $message . "\n";
        } else {
            echo ".";
        }
    }
}

// Main execution
$task = $argv[1] ?? 'all';
$verbose = in_array('-v', $argv) || in_array('--verbose', $argv);

$cron = new CronRunner($verbose);
$cron->run($task);
