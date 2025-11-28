<?php
require_once 'config.php';
require_once 'utils/gmail_mailer.php';

echo "<!DOCTYPE html><html><head><title>Test Gmail</title></head><body>";
echo "<h2>Testing Gmail SMTP</h2>";

// Check if PHPMailer is installed
if (!file_exists('vendor/autoload.php')) {
    echo "<p style='color: red;'>✗ PHPMailer not installed!</p>";
    echo "<p>Run: <code>/Applications/XAMPP/bin/php composer.phar require phpmailer/phpmailer</code></p>";
    exit;
}

// Check configuration
echo "<h3>Configuration Check:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><td>SMTP_USE_GMAIL</td><td>" . (defined('SMTP_USE_GMAIL') ? (SMTP_USE_GMAIL ? 'TRUE' : 'FALSE') : 'NOT DEFINED') . "</td></tr>";
echo "<tr><td>SMTP_GMAIL_USER</td><td>" . (defined('SMTP_GMAIL_USER') ? SMTP_GMAIL_USER : 'NOT DEFINED') . "</td></tr>";
echo "<tr><td>SMTP_GMAIL_PASS</td><td>" . (defined('SMTP_GMAIL_PASS') ? (strlen(SMTP_GMAIL_PASS) > 0 ? 'SET (' . strlen(SMTP_GMAIL_PASS) . ' chars)' : 'EMPTY') : 'NOT DEFINED') . "</td></tr>";
echo "<tr><td>SMTP_GMAIL_HOST</td><td>" . (defined('SMTP_GMAIL_HOST') ? SMTP_GMAIL_HOST : 'NOT DEFINED') . "</td></tr>";
echo "<tr><td>SMTP_GMAIL_PORT</td><td>" . (defined('SMTP_GMAIL_PORT') ? SMTP_GMAIL_PORT : 'NOT DEFINED') . "</td></tr>";
echo "</table>";

$testEmail = 'kevinchu892000@gmail.com';
$testName = 'Kevin';

echo "<hr>";
echo "<p>Sending test email to: <strong>$testEmail</strong></p>";
echo "<p>From: <strong>" . SMTP_GMAIL_USER . "</strong></p>";
echo "<hr>";

$subject = "Test Email - Auction System";
$body = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Hello $testName!</h2>
        <p>This email was sent via <strong>Gmail SMTP</strong>.</p>
        <p>✓ Your email system is working!</p>
        <hr>
        <p style='color: #666; font-size: 12px;'>Sent from Auction System</p>
    </body>
    </html>
";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // Enable verbose debug output
    $mail->SMTPDebug = 2; // Show detailed debug info
    $mail->Debugoutput = function($str, $level) {
        echo "<p style='background: #f0f0f0; padding: 5px; margin: 2px 0; font-family: monospace; font-size: 11px;'>$str</p>";
    };
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_GMAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_GMAIL_USER;
    $mail->Password   = SMTP_GMAIL_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_GMAIL_PORT;
    
    // Disable SSL verification for localhost
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Recipients
    $mail->setFrom(SMTP_GMAIL_USER, SMTP_FROM_NAME);
    $mail->addAddress($testEmail, $testName);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = strip_tags($body);
    
    echo "<h3>Sending Email...</h3>";
    
    $mail->send();
    
    echo "<hr>";
    echo "<p style='color: green; font-size: 20px;'>✓ Email sent successfully!</p>";
    echo "<p>Check inbox at <strong>$testEmail</strong></p>";
    echo "<p><small>(Check spam folder too)</small></p>";
    
} catch (Exception $e) {
    echo "<hr>";
    echo "<p style='color: red; font-size: 20px;'>✗ Failed to send</p>";
    echo "<p><strong>Error:</strong> {$mail->ErrorInfo}</p>";
    echo "<p><strong>Exception:</strong> " . $e->getMessage() . "</p>";
    
    echo "<h3>Troubleshooting Steps:</h3>";
    echo "<ol>";
    echo "<li>Verify App Password in config.php (should be 16 characters without spaces)</li>";
    echo "<li>Check that 2-Step Verification is enabled on Gmail</li>";
    echo "<li>Make sure you're using the correct Gmail address</li>";
    echo "<li>Try generating a new App Password</li>";
    echo "</ol>";
}

echo "</body></html>";
?>