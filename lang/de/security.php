<?php

return [
    'lockout' => [
        'message' => 'Zu viele fehlgeschlagene Versuche. Versuche es in :minutes Minuten erneut.',
    ],

    'api' => [
        'already_enabled' => 'Die Zwei-Faktor-Authentifizierung ist bereits aktiv.',
        'not_started' => 'Starte zuerst die Einrichtung der Zwei-Faktor-Authentifizierung.',
        'invalid_code' => 'Dieser Code ist ungültig.',
        'disable_failed' => 'Das Passwort oder der Code ist nicht korrekt.',
    ],

    'login' => [
        'code_required' => 'Gib den 6-stelligen Code aus deiner Authenticator-App ein.',
    ],

    'forgot' => [
        'link' => 'Passwort vergessen?',
        'title' => 'Passwort zurücksetzen',
        'intro' => 'Gib deine E-Mail-Adresse ein. Wir senden dir einen Link, um ein neues Passwort zu wählen.',
        'submit' => 'Link zum Zurücksetzen senden',
        'sent' => 'Falls zu dieser E-Mail-Adresse ein Konto existiert, ist ein Link unterwegs. Er ist 60 Minuten gültig.',
        'back' => 'Zurück zur Anmeldung',
    ],

    'reset' => [
        'title' => 'Neues Passwort wählen',
        'intro' => 'Wähle ein neues Passwort. Du wirst überall abgemeldet und musst dich neu anmelden.',
        'password' => 'Neues Passwort',
        'confirm' => 'Neues Passwort wiederholen',
        'submit' => 'Neues Passwort speichern',
        'done' => 'Dein Passwort wurde geändert. Melde dich mit dem neuen Passwort an.',
        'invalid_token' => 'Dieser Link zum Zurücksetzen ist ungültig oder abgelaufen.',
        'request_new' => 'Neuen Link anfordern',
    ],

    'verify' => [
        'banner' => 'Bitte bestätige deine E-Mail-Adresse. Bis dahin kannst du keine Auszahlungen beantragen und keine Produkte veröffentlichen.',
        'resend' => 'Bestätigungs-E-Mail erneut senden',
        'sent' => 'Bestätigungs-E-Mail gesendet. Sieh in deinem Posteingang nach.',
        'already' => 'Deine E-Mail-Adresse ist bereits bestätigt.',
        'done' => 'Danke, deine E-Mail-Adresse ist bestätigt.',
        'invalid_link' => 'Dieser Bestätigungslink ist ungültig.',
        'required' => 'Bestätige zuerst deine E-Mail-Adresse, um das zu tun.',
    ],

    'challenge' => [
        'title' => 'Zwei-Faktor-Authentifizierung',
        'intro' => 'Gib den 6-stelligen Code aus deiner Authenticator-App ein.',
        'recovery_intro' => 'Gib einen deiner Wiederherstellungscodes ein. Jeder Code funktioniert nur einmal.',
        'code' => 'Authentifizierungscode',
        'recovery_code' => 'Wiederherstellungscode',
        'submit' => 'Bestätigen und anmelden',
        'use_recovery' => 'Stattdessen einen Wiederherstellungscode verwenden',
        'use_code' => 'Stattdessen einen Authentifizierungscode verwenden',
    ],

    'mail' => [
        'reset_subject' => 'Setze dein Passwort für :app zurück',
        'reset_line' => 'Wir haben eine Anfrage erhalten, das Passwort deines :app-Kontos zurückzusetzen.',
        'reset_button' => 'Neues Passwort wählen',
        'reset_expires' => 'Dieser Link funktioniert einmal und läuft in :minutes Minuten ab.',
        'verify_subject' => 'Bestätige deine E-Mail-Adresse für :app',
        'verify_line' => 'Willkommen bei :app. Bitte bestätige deine E-Mail-Adresse.',
        'verify_button' => 'E-Mail-Adresse bestätigen',
        'verify_expires' => 'Dieser Link läuft in :minutes Minuten ab.',
        'ignore' => 'Wenn du das nicht angefordert hast, kannst du diese E-Mail ignorieren.',
    ],

    'page' => [
        'title' => 'Sicherheit',
        'retry' => 'Erneut versuchen',
        'email_title' => 'E-Mail-Adresse',
        'verified' => 'Bestätigt',
        'unverified' => 'Nicht bestätigt',
        'unverified_help' => 'Du kannst stöbern und kaufen, aber ohne Bestätigung keine Auszahlungen beantragen oder Produkte veröffentlichen.',
    ],

    'twofa' => [
        'title' => 'Zwei-Faktor-Authentifizierung',
        'intro' => 'Füge deiner Anmeldung mit einer Authenticator-App einen zweiten Schritt hinzu. Selbst wenn jemand dein Passwort kennt, kommt er ohne dein Handy nicht hinein.',
        'admin_nudge' => 'Administratorkonten sollten immer die Zwei-Faktor-Authentifizierung nutzen.',
        'admin_banner' => 'Dein Administratorkonto hat keine Zwei-Faktor-Authentifizierung. Aktiviere sie, um den Marktplatz zu schützen.',
        'enable' => 'Zwei-Faktor-Authentifizierung aktivieren',
        'setup_help' => 'Füge dieses Konto deiner Authenticator-App hinzu, indem du den Schlüssel unten eingibst, und tippe dann den 6-stelligen Code der App ein.',
        'secret' => 'Einrichtungsschlüssel',
        'uri' => 'Einrichtungslink',
        'uri_help' => 'Manche Apps können diesen Link direkt öffnen. Er enthält dein Geheimnis, halte ihn daher privat.',
        'copy' => 'Kopieren',
        'copied' => 'Kopiert.',
        'code' => 'Authentifizierungscode',
        'confirm' => 'Bestätigen und aktivieren',
        'enabled' => 'Aktiv',
        'remaining' => 'Verbleibende Wiederherstellungscodes: :count',
        'codes_title' => 'Deine Wiederherstellungscodes',
        'codes_help' => 'Bewahre diese Codes sicher auf. Jeder funktioniert einmal, falls du dein Handy verlierst. Sie werden nur jetzt angezeigt.',
        'saved' => 'Ich habe sie gespeichert',
        'regenerate_title' => 'Wiederherstellungscodes',
        'regenerate_help' => 'Erstelle neue Codes. Die alten funktionieren danach nicht mehr.',
        'regenerate' => 'Neue Codes erstellen',
        'regenerate_confirm' => 'Gib dein Passwort und einen aktuellen Code ein, um neue Wiederherstellungscodes zu erstellen.',
        'disable' => 'Zwei-Faktor-Authentifizierung deaktivieren',
        'disable_help' => 'Gib dein Passwort und einen aktuellen Code ein, um sie zu deaktivieren.',
    ],
];
