<?php

return [
    'lockout' => [
        'message' => 'Troppi tentativi falliti. Riprova tra :minutes minuti.',
    ],

    'api' => [
        'already_enabled' => 'L\'autenticazione a due fattori è già attiva.',
        'not_started' => 'Avvia prima la configurazione dell\'autenticazione a due fattori.',
        'invalid_code' => 'Questo codice non è valido.',
        'disable_failed' => 'La password o il codice non sono corretti.',
    ],

    'login' => [
        'code_required' => 'Inserisci il codice a 6 cifre della tua app di autenticazione.',
    ],

    'forgot' => [
        'link' => 'Hai dimenticato la password?',
        'title' => 'Reimposta la password',
        'intro' => 'Inserisci il tuo indirizzo email e ti invieremo un link per scegliere una nuova password.',
        'submit' => 'Invia il link di reimpostazione',
        'sent' => 'Se esiste un account per questo indirizzo email, un link di reimpostazione è in arrivo. Vale 60 minuti.',
        'back' => 'Torna all\'accesso',
    ],

    'reset' => [
        'title' => 'Scegli una nuova password',
        'intro' => 'Scegli una nuova password. Verrai disconnesso ovunque e dovrai accedere di nuovo.',
        'password' => 'Nuova password',
        'confirm' => 'Ripeti la nuova password',
        'submit' => 'Salva la nuova password',
        'done' => 'La tua password è stata cambiata. Accedi con la nuova password.',
        'invalid_token' => 'Questo link di reimpostazione non è valido o è scaduto.',
        'request_new' => 'Richiedi un nuovo link',
    ],

    'verify' => [
        'banner' => 'Verifica il tuo indirizzo email. Fino ad allora non puoi richiedere pagamenti né pubblicare prodotti.',
        'resend' => 'Invia di nuovo l\'email di verifica',
        'sent' => 'Email di verifica inviata. Controlla la tua posta.',
        'already' => 'Il tuo indirizzo email è già verificato.',
        'done' => 'Grazie, il tuo indirizzo email è verificato.',
        'invalid_link' => 'Questo link di verifica non è valido.',
        'required' => 'Verifica prima il tuo indirizzo email per farlo.',
    ],

    'challenge' => [
        'title' => 'Autenticazione a due fattori',
        'intro' => 'Inserisci il codice a 6 cifre della tua app di autenticazione.',
        'recovery_intro' => 'Inserisci uno dei tuoi codici di recupero. Ogni codice funziona una sola volta.',
        'code' => 'Codice di autenticazione',
        'recovery_code' => 'Codice di recupero',
        'submit' => 'Verifica e accedi',
        'use_recovery' => 'Usa invece un codice di recupero',
        'use_code' => 'Usa invece un codice di autenticazione',
    ],

    'mail' => [
        'reset_subject' => 'Reimposta la tua password di :app',
        'reset_line' => 'Abbiamo ricevuto una richiesta di reimpostazione della password del tuo account :app.',
        'reset_button' => 'Scegli una nuova password',
        'reset_expires' => 'Questo link funziona una sola volta e scade tra :minutes minuti.',
        'verify_subject' => 'Verifica il tuo indirizzo email per :app',
        'verify_line' => 'Benvenuto su :app. Conferma il tuo indirizzo email.',
        'verify_button' => 'Verifica l\'indirizzo email',
        'verify_expires' => 'Questo link scade tra :minutes minuti.',
        'ignore' => 'Se non l\'hai richiesto tu, puoi ignorare questa email.',
    ],

    'page' => [
        'title' => 'Sicurezza',
        'retry' => 'Riprova',
        'email_title' => 'Indirizzo email',
        'verified' => 'Verificato',
        'unverified' => 'Non verificato',
        'unverified_help' => 'Puoi sfogliare e acquistare, ma non richiedere pagamenti né pubblicare prodotti finché non verifichi.',
    ],

    'twofa' => [
        'title' => 'Autenticazione a due fattori',
        'intro' => 'Aggiungi un secondo passaggio al tuo accesso con un\'app di autenticazione. Anche se qualcuno conosce la tua password, non può entrare senza il tuo telefono.',
        'admin_nudge' => 'Gli account amministratore dovrebbero sempre usare l\'autenticazione a due fattori.',
        'admin_banner' => 'Il tuo account amministratore non ha l\'autenticazione a due fattori. Attivala per proteggere il marketplace.',
        'enable' => 'Attiva l\'autenticazione a due fattori',
        'setup_help' => 'Aggiungi questo account alla tua app di autenticazione inserendo la chiave qui sotto, poi digita il codice a 6 cifre mostrato dall\'app.',
        'secret' => 'Chiave di configurazione',
        'uri' => 'Link di configurazione',
        'uri_help' => 'Alcune app possono aprire direttamente questo link. Contiene il tuo segreto, quindi tienilo privato.',
        'copy' => 'Copia',
        'copied' => 'Copiato.',
        'code' => 'Codice di autenticazione',
        'confirm' => 'Conferma e attiva',
        'enabled' => 'Attiva',
        'remaining' => 'Codici di recupero rimasti: :count',
        'codes_title' => 'I tuoi codici di recupero',
        'codes_help' => 'Conserva questi codici in un posto sicuro. Ognuno funziona una volta se perdi il telefono. Vengono mostrati solo adesso.',
        'saved' => 'Li ho salvati',
        'regenerate_title' => 'Codici di recupero',
        'regenerate_help' => 'Crea un nuovo set di codici. I vecchi smetteranno di funzionare.',
        'regenerate' => 'Crea nuovi codici',
        'regenerate_confirm' => 'Inserisci la tua password e un codice attuale per creare nuovi codici di recupero.',
        'disable' => 'Disattiva l\'autenticazione a due fattori',
        'disable_help' => 'Inserisci la tua password e un codice attuale per disattivarla.',
    ],
];
