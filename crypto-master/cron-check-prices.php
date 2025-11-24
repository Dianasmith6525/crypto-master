<?php
/**
 * Price Alert Checker - Cron Job Script
 * This script can be scheduled via cron to run periodically and check prices
 * 
 * Example cron job (run every 5 minutes):
 * */5 * * * * php /path/to/website/cron-check-prices.php
 * 
 * Or via Windows Task Scheduler:
 * C:\php\php.exe C:\path\to\website\cron-check-prices.php
 */

// Include the price checker directly (safer than shell_exec)
require_once __DIR__ . '/check-prices.php';

// Alternative: Use cURL via PHP instead of shell_exec
/*
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://yoursite.com/check-prices.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$output = curl_exec($ch);
curl_close($ch);
*/

// Log the execution
$log_file = __DIR__ . '/logs/alert-checker.log';
$timestamp = date('Y-m-d H:i:s');
$log_message = "[$timestamp] Price check executed\n";

if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

file_put_contents($log_file, $log_message, FILE_APPEND);

echo $output;
?>
