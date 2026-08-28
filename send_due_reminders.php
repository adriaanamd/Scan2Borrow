<?php
/**
 * Scan2Borrow - Due Date Reminder Script
 * 
 * This script sends SMS reminders to students exactly one day before their book's due date.
 * It should be executed daily using Windows Task Scheduler or Cron Job.
 * 
 * Windows Task Scheduler command:
 * C:\xampp\php\php.exe C:\xampp\htdocs\scan2borrow\send_due_reminders.php
 * 
 * Cron Job (Linux/Mac):
 * 0 8 * * * /usr/bin/php /path/to/scan2borrow/send_due_reminders.php
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Set timezone
date_default_timezone_set(APP_TZ);

// Log file for tracking execution
$logFile = __DIR__ . '/logs/due_reminders.log';
$logDir = dirname($logFile);

// Create logs directory if it doesn't exist
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function log_message(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    log_message("=== Starting due date reminder process ===");
    
    $pdo = db();
    $count = process_due_date_reminders();
    
    log_message("Successfully processed $count due date reminder(s)");
    log_message("=== Due date reminder process completed ===\n");
    
    echo "Due date reminders processed: $count\n";
    exit(0);
    
} catch (Throwable $e) {
    log_message("ERROR: " . $e->getMessage());
    log_message("Stack trace: " . $e->getTraceAsString());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}