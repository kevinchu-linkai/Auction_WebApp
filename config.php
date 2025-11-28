<?php
// Database configuration - Using your actual credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'auctionadmin');
define('DB_PASS', 'auctionpassword');
define('DB_NAME', 'auction');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// Email configuration for sending notifications
define('SMTP_FROM_EMAIL', 'auctionnoreply0@gmail.com'); // TODO: Replace with your actual email
define('SMTP_FROM_NAME', 'Auction System');

// Site configuration
define('SITE_URL', 'http://localhost'); // TODO: Change if your site runs on a different URL
define('SITE_NAME', 'Auction System');

// Timezone
date_default_timezone_set('Europe/London');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Gmail SMTP configuration
define('SMTP_USE_GMAIL', true);
define('SMTP_GMAIL_USER', 'auctionnoreply0@gmail.com');
define('SMTP_GMAIL_PASS', 'yuoahevguvetgsuu'); 
define('SMTP_GMAIL_HOST', 'smtp.gmail.com');
define('SMTP_GMAIL_PORT', 587);
?>