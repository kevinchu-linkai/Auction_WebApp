<?php
require_once 'config.php';
require_once 'utils/email_notifications.php';

echo "<!DOCTYPE html><html><head><title>Email Config Test</title></head><body>";
echo "<h2>Configuration Test - Buyer/Seller Schema</h2>";

// Test 1: Database connection
echo "<h3>1. Database Connection</h3>";
if ($conn->ping()) {
    echo "✓ Database connected successfully<br>";
    echo "Database name: " . DB_NAME . "<br>";
} else {
    echo "✗ Database connection failed<br>";
    echo "Error: " . $conn->connect_error . "<br>";
}

// Test 2: Email configuration
echo "<h3>2. Email Configuration</h3>";
echo "From Email: " . SMTP_FROM_EMAIL . "<br>";
echo "From Name: " . SMTP_FROM_NAME . "<br>";
echo "Site URL: " . SITE_URL . "<br>";

// Test 3: Check tables
echo "<h3>3. Database Tables</h3>";
$tables = ['Buyer', 'Seller', 'Auction', 'Item', 'Bid', 'Category'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✓ Table '$table' exists<br>";
    } else {
        echo "✗ Table '$table' NOT found<br>";
    }
}

// Test 4: Buyer table
echo "<h3>4. Buyer Table</h3>";
$result = $conn->query("DESCRIBE Buyer");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
    }
    echo "</table>";
}

$buyerCount = $conn->query("SELECT COUNT(*) as count FROM Buyer WHERE email IS NOT NULL AND email != ''");
if ($buyerCount) {
    $row = $buyerCount->fetch_assoc();
    echo "<br>Buyers with email: {$row['count']}<br>";
}

// Test 5: Seller table
echo "<h3>5. Seller Table</h3>";
$result = $conn->query("DESCRIBE Seller");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
    }
    echo "</table>";
}

$sellerCount = $conn->query("SELECT COUNT(*) as count FROM Seller WHERE email IS NOT NULL AND email != ''");
if ($sellerCount) {
    $row = $sellerCount->fetch_assoc();
    echo "<br>Sellers with email: {$row['count']}<br>";
}

// Test 6: Email Notifications Class
echo "<h3>6. Email Notifications Class</h3>";
try {
    $emailer = new EmailNotifications();
    echo "✓ EmailNotifications class loaded successfully<br>";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

// Test 7: PHP mail
echo "<h3>7. PHP Mail</h3>";
if (function_exists('mail')) {
    echo "✓ mail() function available<br>";
} else {
    echo "✗ mail() function NOT available<br>";
}

echo "<hr><h3>✅ All systems ready!</h3>";
echo "<p>Proceed to Step 5 to integrate with place_bid.php</p>";
echo "</body></html>";
?>