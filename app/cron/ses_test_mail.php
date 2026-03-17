<?php
require __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // === SMTP SETTINGS ===
    $mail->isSMTP();
    $mail->Host       = 'email-smtp.us-west-1.amazonaws.com'; // replace with your SES region
    $mail->SMTPAuth   = true;
    $mail->Username   = 'AKIAVXL56EKN3J7WGOJS'; // from SES SMTP creds
    $mail->Password   = 'BFcFp8K8I44D2kX3lc+5mLtA/QiR7rtMfKDlUJAXfq58'; // from SES SMTP creds
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // === FROM / TO ===
    $mail->setFrom('no-reply@beopp.com', 'Beopp Test');
    $mail->addAddress('keith.e.adams@gmail.com', 'Test User'); // replace with your inbox

    // === MESSAGE ===
    $mail->isHTML(true);
    $mail->Subject = 'SES Test Email from Beopp';
    $mail->Body    = '<p>This is a <strong>test email</strong> sent via Amazon SES SMTP + PHPMailer.</p>';

    $mail->send();
    echo "✅ Test email sent successfully\n";
} catch (Exception $e) {
    echo "❌ Message could not be sent. Error: {$mail->ErrorInfo}\n";
}

