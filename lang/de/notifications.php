<?php

declare(strict_types=1);

return [

    'channels' => [
        'push' => 'Browser-Push',
        'mail' => 'E-Mail',
        'sms' => 'SMS',
    ],

    'events' => [
        'password_changed' => 'Dein Passwort wurde geändert.',
        'email_changed' => 'Die E-Mail-Adresse deines Kontos wurde geändert.',
        'two_factor_enabled' => 'Die Zwei-Faktor-Anmeldung wurde eingeschaltet.',
        'two_factor_disabled' => 'Die Zwei-Faktor-Anmeldung wurde ausgeschaltet.',
        'backup_codes_regenerated' => 'Deine Backup-Codes wurden ersetzt.',
        'social_linked' => 'Ein Anmeldedienst wurde mit deinem Konto verbunden.',
        'social_unlinked' => 'Ein Anmeldedienst wurde von deinem Konto getrennt.',
    ],

    'security' => [
        'title' => 'Sicherheitshinweis zu :app',
        'where' => 'Von :device, IP :ip.',
        'action' => 'Sicherheitseinstellungen ansehen',
        'disclaimer' => 'Warst du das selbst, ist nichts zu tun.',
        'sms_tail' => 'Warst du das nicht, sichere dein Konto sofort.',
    ],

    'inviteAccepted' => [
        'title' => 'Einladung angenommen',
        'body' => ':name (:email) hat deine Einladung angenommen.',
        'action' => 'Team ansehen',
    ],

    'phone' => [
        'code' => ':code ist dein Bestätigungscode für :app.',
        'invalid' => 'Gib die Nummer international an, mit Pluszeichen und Ländervorwahl.',
        'invalid_code' => 'Der Code stimmt nicht oder ist abgelaufen. Fordere einen neuen an.',
        'too_soon' => 'Warte noch :seconds Sekunden, bevor du einen neuen Code anforderst.',
    ],

];
