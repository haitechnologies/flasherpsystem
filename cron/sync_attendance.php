#!/usr/bin/env php
<?php

/**
 * Attendance Sync Cron
 *
 * CLI script run daily (recommended: 23:30) to pull attendance logs
 * from all configured ZKTeco devices and derive daily attendance records.
 *
 * Usage: php cron/sync_attendance.php
 *
 * Example cron entry (Linux):
 *   30 23 * * * php /path/to/cron/sync_attendance.php >> /path/to/logs/attendance_sync.log 2>&1
 *
 * Windows Task Scheduler:
 *   Action: Start a program
 *   Program: C:\xampp\php\php.exe
 *   Arguments: G:\xampp\htdocs\flasherpsystem\cron\sync_attendance.php
 */

declare(strict_types=1);

// Bootstrap — minimal DB setup (same pattern as check_annual_leave.php)
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

$envCandidates = [
    dirname(__DIR__),
    dirname(__DIR__, 2),
    dirname(__DIR__, 3),
];

$envLoaded = false;
foreach ($envCandidates as $candidateDir) {
    if (is_file($candidateDir . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable($candidateDir);
        $dotenv->safeLoad();
        $envLoaded = true;
        break;
    }
}
if (!$envLoaded) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

$appEnv = strtolower(getenv('APP_ENV') ?: '');
error_reporting(E_ALL);
ini_set('display_errors', $appEnv === 'development' ? '1' : '0');

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Dubai');

use App\Core\Database;
use App\Core\DB;
use App\Repository\AttendanceDeviceRepository;
use App\Repository\AttendancePunchRepository;
use App\Service\AttendanceSyncService;

$db = new Database();

// Log helper
$log = static function (string $message): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
};

$log('Starting attendance sync...');

// Instantiate dependencies manually (no DI container in CLI mode)
$deviceRepo = new AttendanceDeviceRepository($db);
$punchRepo = new AttendancePunchRepository($db);
$syncService = new AttendanceSyncService($db, $deviceRepo, $punchRepo);

// Run sync for all devices
$results = $syncService->syncAll();

$totalPulled = 0;
$totalInserted = 0;
$totalDerived = 0;
$deviceCount = count($results);
$errorCount = 0;

foreach ($results as $deviceId => $result) {
    $name = $result['device_name'] ?? "ID:{$deviceId}";
    $status = $result['status'] ?? 'unknown';

    if ($status === 'ok') {
        $pulled = (int)($result['punches_pulled'] ?? 0);
        $inserted = (int)($result['punches_inserted'] ?? 0);
        $derived = (int)($result['attendance_derived'] ?? 0);
        $totalPulled += $pulled;
        $totalInserted += $inserted;
        $totalDerived += $derived;
        $log("  [OK] {$name}: {$pulled} logs pulled, {$inserted} new punches, {$derived} attendance records derived");
    } else {
        $errorCount++;
        $error = $result['error'] ?? 'Unknown error';
        $log("  [FAIL] {$name}: {$error}");
    }
}

$log("Sync complete: {$deviceCount} devices, {$errorCount} errors, "
    . "{$totalPulled} total logs, {$totalInserted} new punches, "
    . "{$totalDerived} attendance records");
