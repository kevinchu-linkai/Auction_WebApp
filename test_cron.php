<?php
// Security: Only allow from localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost'])) {
    die('Access denied');
}

echo "<!DOCTYPE html><html><head><title>Test Cron Job</title></head><body>";
echo "<h2>Manual Cron Job Test</h2>";
echo "<pre>";

include 'cron/check_expired_auctions.php';

echo "</pre>";
echo "<hr>";
echo "<p><a href='test_cron.php'>Run Again</a></p>";
echo "</body></html>";
?>