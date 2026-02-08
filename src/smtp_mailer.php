<?php
declare(strict_types=1);

require_once APP_ROOT . '/lib/PHPMailer/Exception.php';
require_once APP_ROOT . '/lib/PHPMailer/SMTP.php';
require_once APP_ROOT . '/lib/PHPMailer/PHPMailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_email_smtp(PDO $db, string $toEmail, string $toName, string $subject, string $textBody, ?string $htmlBody = null): void
{
    $cfg = get_smtp_settings($db);
    if (empty($cfg['enabled'])) {
        throw new RuntimeException('SMTP is not enabled.');
    }
    if ($cfg['host'] === '' || $cfg['from_email'] === '') {
        throw new RuntimeException('SMTP settings incomplete.');
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = (int)$cfg['port'];

        $enc = strtolower((string)$cfg['encryption']);
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
        }

        $hasAuth = ($cfg['username'] !== '' || $cfg['password'] !== '');
        $mail->SMTPAuth = $hasAuth;
        if ($hasAuth) {
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'];
        }

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;

        if ($htmlBody !== null && $htmlBody !== '') {
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
        } else {
            $mail->isHTML(false);
            $mail->Body = $textBody;
        }

        $mail->send();
    } catch (Exception $e) {
        throw new RuntimeException('SMTP send failed: ' . $mail->ErrorInfo);
    }
}

function send_verification_email(PDO $db, string $email, string $verifyUrl): void
{
    $subject = 'Verify your email for Karaoke OS';
    $text = "Click to verify your email:\n\n{$verifyUrl}\n\nThis link expires in 24 hours.";
    $html = '<p>Click to verify your email:</p><p><a href="' . e($verifyUrl) . '">' . e($verifyUrl) . '</a></p><p><small>This link expires in 24 hours.</small></p>';
    send_email_smtp($db, $email, $email, $subject, $text, $html);
}

