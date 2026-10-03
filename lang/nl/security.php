<?php

return [
    'lockout' => [
        'message' => 'Te veel mislukte pogingen. Probeer het over :minutes minuten opnieuw.',
    ],

    'api' => [
        'already_enabled' => 'Tweestapsverificatie staat al aan.',
        'not_started' => 'Start eerst de installatie van tweestapsverificatie.',
        'invalid_code' => 'Deze code is niet geldig.',
        'disable_failed' => 'Het wachtwoord of de code klopt niet.',
    ],

    'login' => [
        'code_required' => 'Voer de 6-cijferige code uit je authenticator-app in.',
    ],

    'forgot' => [
        'link' => 'Wachtwoord vergeten?',
        'title' => 'Wachtwoord opnieuw instellen',
        'intro' => 'Vul je e-mailadres in, dan sturen we je een link om een nieuw wachtwoord te kiezen.',
        'submit' => 'Resetlink versturen',
        'sent' => 'Als er een account bestaat voor dit e-mailadres, is er een resetlink onderweg. De link werkt 60 minuten.',
        'back' => 'Terug naar inloggen',
    ],

    'reset' => [
        'title' => 'Kies een nieuw wachtwoord',
        'intro' => 'Kies een nieuw wachtwoord. Je wordt overal uitgelogd en moet opnieuw inloggen.',
        'password' => 'Nieuw wachtwoord',
        'confirm' => 'Herhaal het nieuwe wachtwoord',
        'submit' => 'Nieuw wachtwoord opslaan',
        'done' => 'Je wachtwoord is gewijzigd. Log in met je nieuwe wachtwoord.',
        'invalid_token' => 'Deze resetlink is ongeldig of verlopen.',
        'request_new' => 'Nieuwe link aanvragen',
    ],

    'verify' => [
        'banner' => 'Bevestig je e-mailadres. Tot die tijd kun je geen uitbetalingen aanvragen en geen producten publiceren.',
        'resend' => 'Verificatiemail opnieuw versturen',
        'sent' => 'Verificatiemail verstuurd. Kijk in je inbox.',
        'already' => 'Je e-mailadres is al bevestigd.',
        'done' => 'Bedankt, je e-mailadres is bevestigd.',
        'invalid_link' => 'Deze verificatielink is niet geldig.',
        'required' => 'Bevestig eerst je e-mailadres om dit te doen.',
    ],

    'challenge' => [
        'title' => 'Tweestapsverificatie',
        'intro' => 'Voer de 6-cijferige code uit je authenticator-app in.',
        'recovery_intro' => 'Voer een van je herstelcodes in. Elke code werkt maar één keer.',
        'code' => 'Verificatiecode',
        'recovery_code' => 'Herstelcode',
        'submit' => 'Verifiëren en inloggen',
        'use_recovery' => 'Gebruik in plaats daarvan een herstelcode',
        'use_code' => 'Gebruik in plaats daarvan een verificatiecode',
    ],

    'mail' => [
        'reset_subject' => 'Stel je wachtwoord voor :app opnieuw in',
        'reset_line' => 'We hebben een verzoek ontvangen om het wachtwoord van je :app-account opnieuw in te stellen.',
        'reset_button' => 'Nieuw wachtwoord kiezen',
        'reset_expires' => 'Deze link werkt één keer en verloopt over :minutes minuten.',
        'verify_subject' => 'Bevestig je e-mailadres voor :app',
        'verify_line' => 'Welkom bij :app. Bevestig je e-mailadres.',
        'verify_button' => 'E-mailadres bevestigen',
        'verify_expires' => 'Deze link verloopt over :minutes minuten.',
        'ignore' => 'Heb je dit niet aangevraagd, dan kun je deze e-mail negeren.',
    ],

    'page' => [
        'title' => 'Beveiliging',
        'retry' => 'Opnieuw proberen',
        'email_title' => 'E-mailadres',
        'verified' => 'Bevestigd',
        'unverified' => 'Niet bevestigd',
        'unverified_help' => 'Je kunt bladeren en kopen, maar je kunt geen uitbetalingen aanvragen of producten publiceren totdat je bevestigt.',
    ],

    'twofa' => [
        'title' => 'Tweestapsverificatie',
        'intro' => 'Voeg met een authenticator-app een tweede stap toe aan je login. Ook als iemand je wachtwoord kent, komt hij er zonder jouw telefoon niet in.',
        'admin_nudge' => 'Beheerdersaccounts moeten altijd tweestapsverificatie gebruiken.',
        'admin_banner' => 'Je beheerdersaccount heeft geen tweestapsverificatie. Zet het aan om de marktplaats te beschermen.',
        'enable' => 'Tweestapsverificatie aanzetten',
        'setup_help' => 'Voeg dit account toe aan je authenticator-app met de sleutel hieronder en typ daarna de 6-cijferige code die de app toont.',
        'secret' => 'Installatiesleutel',
        'uri' => 'Installatielink',
        'uri_help' => 'Sommige apps kunnen deze link direct openen. Hij bevat je geheim, houd hem dus privé.',
        'copy' => 'Kopiëren',
        'copied' => 'Gekopieerd.',
        'code' => 'Verificatiecode',
        'confirm' => 'Bevestigen en aanzetten',
        'enabled' => 'Aan',
        'remaining' => 'Resterende herstelcodes: :count',
        'codes_title' => 'Je herstelcodes',
        'codes_help' => 'Bewaar deze codes op een veilige plek. Elke code werkt één keer als je je telefoon kwijtraakt. Ze worden alleen nu getoond.',
        'saved' => 'Ik heb ze bewaard',
        'regenerate_title' => 'Herstelcodes',
        'regenerate_help' => 'Maak een nieuwe set codes. De oude werken dan niet meer.',
        'regenerate' => 'Nieuwe codes maken',
        'regenerate_confirm' => 'Voer je wachtwoord en een huidige code in om nieuwe herstelcodes te maken.',
        'disable' => 'Tweestapsverificatie uitzetten',
        'disable_help' => 'Voer je wachtwoord en een huidige code in om het uit te zetten.',
    ],
];
