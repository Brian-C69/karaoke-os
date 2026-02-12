<?php
declare(strict_types=1);

require_once APP_ROOT . '/lib/PHPMailer/Exception.php';
require_once APP_ROOT . '/lib/PHPMailer/SMTP.php';
require_once APP_ROOT . '/lib/PHPMailer/PHPMailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_email_smtp(
    PDO $db,
    string $toEmail,
    string $toName,
    string $subject,
    string $textBody,
    ?string $htmlBody = null,
    ?string $replyToEmail = null,
    ?string $replyToName = null
): void
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
        $replyToEmail = $replyToEmail !== null ? trim($replyToEmail) : '';
        if ($replyToEmail !== '' && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyToEmail, $replyToName !== null ? (string)$replyToName : $replyToEmail);
        }
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

function send_contact_form_email(PDO $db, array $data): void
{
    $contact = get_contact_settings($db);
    $toEmail = trim((string)($contact['to_email'] ?? ''));
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Contact form recipient is not configured.');
    }
    $toName = trim((string)($contact['to_name'] ?? ''));
    if ($toName === '') $toName = $toEmail;

    $type = trim((string)($data['type'] ?? ''));
    $message = trim((string)($data['message'] ?? ''));
    $songTitle = trim((string)($data['song_title'] ?? ''));
    $songArtist = trim((string)($data['song_artist'] ?? ''));
    $songLink = trim((string)($data['song_link'] ?? ''));
    $fromEmail = trim((string)($data['from_email'] ?? ''));
    $fromName = trim((string)($data['from_name'] ?? ''));
    $username = trim((string)($data['username'] ?? ''));
    $userId = (int)($data['user_id'] ?? 0);
    $ip = trim((string)($data['ip'] ?? ''));
    $ua = trim((string)($data['ua'] ?? ''));

    $subject = '[Karaoke OS] ' . ($type !== '' ? $type : 'Contact');

    $lines = [];
    $lines[] = "Type: " . ($type !== '' ? $type : 'Contact');
    if ($fromName !== '' || $fromEmail !== '') {
        $lines[] = "From: " . trim(($fromName !== '' ? $fromName : '') . ($fromEmail !== '' ? " <{$fromEmail}>" : ''));
    }
    if ($username !== '' || $userId > 0) {
        $lines[] = "User: " . ($username !== '' ? $username : '-') . ($userId > 0 ? " (#{$userId})" : '');
    }
    if ($songTitle !== '' || $songArtist !== '' || $songLink !== '') {
        $lines[] = "";
        $lines[] = "Song request details:";
        if ($songTitle !== '') $lines[] = "  Title: {$songTitle}";
        if ($songArtist !== '') $lines[] = "  Artist: {$songArtist}";
        if ($songLink !== '') $lines[] = "  Link: {$songLink}";
    }
    $lines[] = "";
    $lines[] = "Message:";
    $lines[] = $message;
    $lines[] = "";
    $lines[] = "Meta:";
    if ($ip !== '') $lines[] = "  IP: {$ip}";
    if ($ua !== '') $lines[] = "  UA: {$ua}";

    $text = implode("\n", $lines);

    $html = '<h3>Karaoke OS Contact</h3>';
    $html .= '<p><strong>Type:</strong> ' . e($type !== '' ? $type : 'Contact') . '</p>';
    if ($fromName !== '' || $fromEmail !== '') {
        $html .= '<p><strong>From:</strong> ' . e(trim(($fromName !== '' ? $fromName : '') . ($fromEmail !== '' ? " <{$fromEmail}>" : ''))) . '</p>';
    }
    if ($username !== '' || $userId > 0) {
        $html .= '<p><strong>User:</strong> ' . e(($username !== '' ? $username : '-') . ($userId > 0 ? " (#{$userId})" : '')) . '</p>';
    }
    if ($songTitle !== '' || $songArtist !== '' || $songLink !== '') {
        $html .= '<h4>Song request details</h4><ul>';
        if ($songTitle !== '') $html .= '<li><strong>Title:</strong> ' . e($songTitle) . '</li>';
        if ($songArtist !== '') $html .= '<li><strong>Artist:</strong> ' . e($songArtist) . '</li>';
        if ($songLink !== '') $html .= '<li><strong>Link:</strong> ' . e($songLink) . '</li>';
        $html .= '</ul>';
    }
    $html .= '<h4>Message</h4><pre style="white-space:pre-wrap;margin:0">' . e($message) . '</pre>';
    if ($ip !== '' || $ua !== '') {
        $html .= '<hr><small>';
        if ($ip !== '') $html .= 'IP: ' . e($ip) . '<br>';
        if ($ua !== '') $html .= 'UA: ' . e($ua);
        $html .= '</small>';
    }

    send_email_smtp($db, $toEmail, $toName, $subject, $text, $html, $fromEmail !== '' ? $fromEmail : null, $fromName !== '' ? $fromName : null);
}
