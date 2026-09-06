<?php

declare(strict_types=1);

return [

    'failed' => 'Diese Zugangsdaten sind nicht bekannt.',
    'password' => 'Das Passwort ist falsch.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte in :seconds Sekunden erneut versuchen.',

    'unauthenticated' => 'Du bist nicht angemeldet.',
    'login_successful' => 'Angemeldet.',
    'registration_closed' => 'Die Registrierung ist nur auf Einladung möglich.',
    'email_not_verified' => 'Bitte zuerst die E-Mail-Adresse bestätigen.',
    'already_verified' => 'Diese Adresse ist bereits bestätigt.',
    'verification_sent' => 'Ein neuer Bestätigungslink wurde gesendet.',
    'password_updated' => 'Das Passwort wurde geändert.',
    'password_confirmed' => 'Passwort bestätigt.',
    'password_confirmation_required' => 'Bitte das Passwort bestätigen, um fortzufahren.',
    'invalid_password' => 'Dieses Passwort ist nicht korrekt.',
    'too_many_verification_attempts' => 'Zu viele Versuche. In :seconds Sekunden erneut versuchen.',

    'totp_required' => 'Gib den Code aus deiner Authenticator-App ein.',
    'invalid_totp_code' => 'Dieser Code ist ungültig.',
    'totp_already_enabled' => 'Die Zwei-Faktor-Authentifizierung ist bereits aktiv.',
    'totp_not_enabled' => 'Die Zwei-Faktor-Authentifizierung ist nicht aktiv.',
    'totp_setup_expired' => 'Die Einrichtung ist abgelaufen. Bitte neu starten.',
    'totp_disabled' => 'Die Zwei-Faktor-Authentifizierung wurde deaktiviert.',

    'not_impersonating' => 'Diese Sitzung ist keine Impersonation.',
    'not_while_impersonating' => 'Während einer Impersonation nicht verfügbar.',
    'last_owner' => 'Der letzte Owner kann nicht entfernt oder herabgestuft werden.',

    'invite_invalid' => 'Diese Einladung ist nicht mehr gültig.',
    'invite_email_mismatch' => 'Diese Einladung wurde an eine andere E-Mail-Adresse gesendet.',
    'invite_requires_account' => 'Erstelle ein Konto, um diese Einladung anzunehmen.',
    'invite_subject' => 'Du wurdest zu :app eingeladen',
    'invite_line' => 'Du wurdest eingeladen, :app beizutreten.',
    'invite_line_by' => ':name hat dich eingeladen, :app beizutreten.',
    'invite_action' => 'Einladung annehmen',
    'invite_expires' => 'Diese Einladung läuft am :date ab.',

    'email_taken' => 'Diese Adresse gehört inzwischen zu einem anderen Konto.',
    'email_change_requested' => 'Prüfe die neue Adresse auf den Bestätigungslink.',
    'email_change_subject' => 'Bestätige deine neue Adresse für :app',
    'email_change_line' => 'Öffne den Link unten, um dein :app-Konto auf diese Adresse umzuziehen.',
    'email_change_action' => 'Adresse bestätigen',
    'email_change_expires' => 'Der Link läuft am :date ab.',
    'email_change_ignore' => 'Wenn du das nicht angefordert hast, ignoriere diese Mail. Es hat sich nichts geändert.',
    'email_change_notice_subject' => 'Für dein :app-Konto wurde eine Adressänderung angefordert',
    'email_change_notice_line' => 'Jemand möchte dein Konto auf :email umziehen. Der Umzug findet erst statt, wenn diese Adresse bestätigt.',
    'email_change_notice_warning' => 'Warst du das nicht, ändere jetzt dein Passwort und melde die anderen Geräte ab.',
    'email_changed_subject' => 'Die Adresse deines :app-Kontos hat sich geändert',
    'email_changed_line' => 'Dein Konto nutzt jetzt :email. Mit dieser Adresse ist keine Anmeldung mehr möglich.',

    'unknown_device' => 'Unbekanntes Gerät',
    'session_revoked' => 'Dieses Gerät wurde von einer anderen Sitzung abgemeldet.',
    'session_is_current' => 'Das Gerät, das du gerade benutzt, kannst du nicht abmelden.',

];
