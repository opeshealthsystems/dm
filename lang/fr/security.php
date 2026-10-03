<?php

return [
    'lockout' => [
        'message' => 'Trop de tentatives échouées. Réessayez dans :minutes minutes.',
    ],

    'api' => [
        'already_enabled' => 'L\'authentification à deux facteurs est déjà activée.',
        'not_started' => 'Commencez d\'abord la configuration de l\'authentification à deux facteurs.',
        'invalid_code' => 'Ce code n\'est pas valide.',
        'disable_failed' => 'Le mot de passe ou le code est incorrect.',
    ],

    'login' => [
        'code_required' => 'Saisissez le code à 6 chiffres de votre application d\'authentification.',
    ],

    'forgot' => [
        'link' => 'Mot de passe oublié ?',
        'title' => 'Réinitialiser votre mot de passe',
        'intro' => 'Saisissez votre adresse e-mail et nous vous enverrons un lien pour choisir un nouveau mot de passe.',
        'submit' => 'Envoyer le lien de réinitialisation',
        'sent' => 'Si un compte existe pour cette adresse e-mail, un lien de réinitialisation est en route. Il est valable 60 minutes.',
        'back' => 'Retour à la connexion',
    ],

    'reset' => [
        'title' => 'Choisir un nouveau mot de passe',
        'intro' => 'Choisissez un nouveau mot de passe. Vous serez déconnecté partout et devrez vous reconnecter.',
        'password' => 'Nouveau mot de passe',
        'confirm' => 'Répétez le nouveau mot de passe',
        'submit' => 'Enregistrer le nouveau mot de passe',
        'done' => 'Votre mot de passe a été modifié. Connectez-vous avec votre nouveau mot de passe.',
        'invalid_token' => 'Ce lien de réinitialisation est invalide ou a expiré.',
        'request_new' => 'Demander un nouveau lien',
    ],

    'verify' => [
        'banner' => 'Veuillez vérifier votre adresse e-mail. D\'ici là, vous ne pouvez ni demander de versements ni publier de produits.',
        'resend' => 'Renvoyer l\'e-mail de vérification',
        'sent' => 'E-mail de vérification envoyé. Consultez votre boîte de réception.',
        'already' => 'Votre adresse e-mail est déjà vérifiée.',
        'done' => 'Merci, votre adresse e-mail est vérifiée.',
        'invalid_link' => 'Ce lien de vérification n\'est pas valide.',
        'required' => 'Vérifiez d\'abord votre adresse e-mail pour faire cela.',
    ],

    'challenge' => [
        'title' => 'Authentification à deux facteurs',
        'intro' => 'Saisissez le code à 6 chiffres de votre application d\'authentification.',
        'recovery_intro' => 'Saisissez l\'un de vos codes de récupération. Chaque code ne fonctionne qu\'une seule fois.',
        'code' => 'Code d\'authentification',
        'recovery_code' => 'Code de récupération',
        'submit' => 'Vérifier et se connecter',
        'use_recovery' => 'Utiliser plutôt un code de récupération',
        'use_code' => 'Utiliser plutôt un code d\'authentification',
    ],

    'mail' => [
        'reset_subject' => 'Réinitialisez votre mot de passe :app',
        'reset_line' => 'Nous avons reçu une demande de réinitialisation du mot de passe de votre compte :app.',
        'reset_button' => 'Choisir un nouveau mot de passe',
        'reset_expires' => 'Ce lien ne fonctionne qu\'une fois et expire dans :minutes minutes.',
        'verify_subject' => 'Vérifiez votre adresse e-mail pour :app',
        'verify_line' => 'Bienvenue sur :app. Veuillez confirmer votre adresse e-mail.',
        'verify_button' => 'Vérifier l\'adresse e-mail',
        'verify_expires' => 'Ce lien expire dans :minutes minutes.',
        'ignore' => 'Si vous n\'êtes pas à l\'origine de cette demande, vous pouvez ignorer cet e-mail.',
    ],

    'page' => [
        'title' => 'Sécurité',
        'retry' => 'Réessayer',
        'email_title' => 'Adresse e-mail',
        'verified' => 'Vérifiée',
        'unverified' => 'Non vérifiée',
        'unverified_help' => 'Vous pouvez parcourir et acheter, mais pas demander de versements ni publier de produits tant que vous n\'avez pas vérifié.',
    ],

    'twofa' => [
        'title' => 'Authentification à deux facteurs',
        'intro' => 'Ajoutez une deuxième étape à votre connexion avec une application d\'authentification. Même si quelqu\'un connaît votre mot de passe, il ne peut pas entrer sans votre téléphone.',
        'admin_nudge' => 'Les comptes administrateur devraient toujours utiliser l\'authentification à deux facteurs.',
        'admin_banner' => 'Votre compte administrateur n\'a pas d\'authentification à deux facteurs. Activez-la pour protéger la place de marché.',
        'enable' => 'Activer l\'authentification à deux facteurs',
        'setup_help' => 'Ajoutez ce compte à votre application d\'authentification en saisissant la clé ci-dessous, puis tapez le code à 6 chiffres affiché par l\'application.',
        'secret' => 'Clé de configuration',
        'uri' => 'Lien de configuration',
        'uri_help' => 'Certaines applications peuvent ouvrir ce lien directement. Il contient votre secret, gardez-le privé.',
        'copy' => 'Copier',
        'copied' => 'Copié.',
        'code' => 'Code d\'authentification',
        'confirm' => 'Confirmer et activer',
        'enabled' => 'Activée',
        'remaining' => 'Codes de récupération restants : :count',
        'codes_title' => 'Vos codes de récupération',
        'codes_help' => 'Conservez ces codes en lieu sûr. Chacun fonctionne une fois si vous perdez votre téléphone. Ils ne sont affichés que maintenant.',
        'saved' => 'Je les ai enregistrés',
        'regenerate_title' => 'Codes de récupération',
        'regenerate_help' => 'Créez un nouvel ensemble de codes. Les anciens cesseront de fonctionner.',
        'regenerate' => 'Créer de nouveaux codes',
        'regenerate_confirm' => 'Saisissez votre mot de passe et un code actuel pour créer de nouveaux codes de récupération.',
        'disable' => 'Désactiver l\'authentification à deux facteurs',
        'disable_help' => 'Saisissez votre mot de passe et un code actuel pour la désactiver.',
    ],
];
