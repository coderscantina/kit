<?php

declare(strict_types=1);

return [

    'people' => [
        'title' => 'Personen',
        // Reihenfolge ist die Spaltenreihenfolge in der Datei.
        'columns' => [
            'name' => 'Name',
            'email' => 'E-Mail',
            'role' => 'Rolle',
            'status' => 'Status',
            'kind' => 'Typ',
            'joined' => 'Dabei seit',
            'expires' => 'Einladung läuft ab',
        ],
        'states' => [
            'active' => 'Aktiv',
            'pending' => 'Eingeladen',
            'expired' => 'Abgelaufen',
        ],
        'kinds' => [
            'user' => 'Konto',
            'invite' => 'Einladung',
        ],
    ],

];
