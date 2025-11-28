<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';

class GmailMailer {
    
    public function sendEmail($to, $toName, $subject, $htmlBody) {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_GMAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_GMAIL_USER;
            $mail->Password   = SMTP_GMAIL_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_GMAIL_PORT;
            
            // Disable SSL verification for localhost testing (remove on production)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Recipients
            $mail->setFrom(SMTP_GMAIL_USER, SMTP_FROM_NAME);
            $mail->addAddress($to, $toName);
            $mail->addReplyTo(SMTP_GMAIL_USER, SMTP_FROM_NAME);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);
            
            $mail->send();
            error_log("Email sent successfully to: $to");
            return true;
            
        } catch (Exception $e) {
            error_log("Email failed: {$mail->ErrorInfo}");
            return false;
        }
    }
}
?>