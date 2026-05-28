<?php

declare(strict_types=1);

final class Mailer
{
    public static function sendPasswordResetMail(array $config, array $user, string $resetUrl): void
    {
        $fromEmail = (string)($config['password_reset']['from_email'] ?? 'support@die-kerwe.de');
        $fromName = (string)($config['password_reset']['from_name'] ?? 'Die Kerwe Benutzerverwaltung');
        $subject = (string)($config['password_reset']['subject'] ?? 'Passwort zurücksetzen');

        $to = (string)($user['mail'] ?? '');

        if ($to === '') {
            throw new RuntimeException('Empfängeradresse fehlt.');
        }

        $displayName = (string)($user['display_name'] ?? $user['uid'] ?? 'Benutzer');

        $body =
            "Hallo " . $displayName . ",\n\n" .
            "du hast das Zurücksetzen deines Passworts angefordert.\n\n" .
            "Über diesen Link kannst du ein neues Passwort eingeben:\n" .
            $resetUrl . "\n\n" .
            "Der Link ist 30 Minuten gültig.\n\n" .
            "Falls du diese Anfrage nicht gestellt hast, kannst du diese E-Mail ignorieren.\n";

        $headers = [
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        if (!mail($to, $subject, $body, implode("\r\n", $headers))) {
            throw new RuntimeException('Reset-E-Mail konnte nicht versendet werden.');
        }
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

public static function sendPasswordResetApprovalMail(array $config, array $resetRequest, string $approvalUrl): void
{
    $fromEmail = (string)($config['password_reset']['from_email'] ?? 'support@die-kerwe.de');
    $fromName = (string)($config['password_reset']['from_name'] ?? 'Die Kerwe Benutzerverwaltung');

    $recipients = $config['password_reset']['admin_notify_emails'] ?? [];

    if (!is_array($recipients) || $recipients === []) {
        throw new RuntimeException('Keine Admin-Empfänger für Passwortreset-Freigaben konfiguriert.');
    }

    $subject = 'Passwortänderung freigeben';

    $uid = (string)($resetRequest['uid'] ?? '');
    $mail = (string)($resetRequest['mail'] ?? '');
    $displayName = (string)($resetRequest['display_name'] ?? $uid);

    $body =
        "Es liegt eine Passwortänderung zur Freigabe vor.\n\n" .
        "Benutzer: " . $displayName . "\n" .
        "UID: " . $uid . "\n" .
        "E-Mail: " . $mail . "\n\n" .
        "Freigabe-Link:\n" .
        $approvalUrl . "\n\n" .
        "Der Link ist maximal 24 Stunden gültig. Das neue Passwort wird nicht angezeigt.\n";

    $headers = [
        'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    foreach ($recipients as $recipient) {
        mail((string)$recipient, $subject, $body, implode("\r\n", $headers));
    }
}





}



