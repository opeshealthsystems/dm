<?php

return [
    'lockout' => [
        'message' => 'Demasiados intentos fallidos. Inténtalo de nuevo en :minutes minutos.',
    ],

    'api' => [
        'already_enabled' => 'La autenticación de dos factores ya está activada.',
        'not_started' => 'Primero inicia la configuración de la autenticación de dos factores.',
        'invalid_code' => 'Ese código no es válido.',
        'disable_failed' => 'La contraseña o el código no son correctos.',
    ],

    'login' => [
        'code_required' => 'Introduce el código de 6 dígitos de tu aplicación de autenticación.',
    ],

    'forgot' => [
        'link' => '¿Olvidaste tu contraseña?',
        'title' => 'Restablecer tu contraseña',
        'intro' => 'Introduce tu correo electrónico y te enviaremos un enlace para elegir una nueva contraseña.',
        'submit' => 'Enviar enlace de restablecimiento',
        'sent' => 'Si existe una cuenta con ese correo, un enlace de restablecimiento va en camino. Funciona durante 60 minutos.',
        'back' => 'Volver al inicio de sesión',
    ],

    'reset' => [
        'title' => 'Elige una nueva contraseña',
        'intro' => 'Elige una nueva contraseña. Se cerrará tu sesión en todas partes y tendrás que iniciarla de nuevo.',
        'password' => 'Nueva contraseña',
        'confirm' => 'Repite la nueva contraseña',
        'submit' => 'Guardar nueva contraseña',
        'done' => 'Tu contraseña se ha cambiado. Inicia sesión con tu nueva contraseña.',
        'invalid_token' => 'Este enlace de restablecimiento no es válido o ha caducado.',
        'request_new' => 'Solicitar un nuevo enlace',
    ],

    'verify' => [
        'banner' => 'Verifica tu correo electrónico. Hasta entonces no puedes solicitar pagos ni publicar productos.',
        'resend' => 'Reenviar correo de verificación',
        'sent' => 'Correo de verificación enviado. Revisa tu bandeja de entrada.',
        'already' => 'Tu correo electrónico ya está verificado.',
        'done' => 'Gracias, tu correo electrónico está verificado.',
        'invalid_link' => 'Este enlace de verificación no es válido.',
        'required' => 'Verifica primero tu correo electrónico para hacer esto.',
    ],

    'challenge' => [
        'title' => 'Autenticación de dos factores',
        'intro' => 'Introduce el código de 6 dígitos de tu aplicación de autenticación.',
        'recovery_intro' => 'Introduce uno de tus códigos de recuperación. Cada código solo funciona una vez.',
        'code' => 'Código de autenticación',
        'recovery_code' => 'Código de recuperación',
        'submit' => 'Verificar e iniciar sesión',
        'use_recovery' => 'Usar un código de recuperación',
        'use_code' => 'Usar un código de autenticación',
    ],

    'mail' => [
        'reset_subject' => 'Restablece tu contraseña de :app',
        'reset_line' => 'Hemos recibido una solicitud para restablecer la contraseña de tu cuenta de :app.',
        'reset_button' => 'Elegir una nueva contraseña',
        'reset_expires' => 'Este enlace funciona una sola vez y caduca en :minutes minutos.',
        'verify_subject' => 'Verifica tu correo electrónico en :app',
        'verify_line' => 'Te damos la bienvenida a :app. Confirma tu correo electrónico.',
        'verify_button' => 'Verificar correo electrónico',
        'verify_expires' => 'Este enlace caduca en :minutes minutos.',
        'ignore' => 'Si no lo has solicitado tú, puedes ignorar este correo.',
    ],

    'page' => [
        'title' => 'Seguridad',
        'retry' => 'Reintentar',
        'email_title' => 'Correo electrónico',
        'verified' => 'Verificado',
        'unverified' => 'Sin verificar',
        'unverified_help' => 'Puedes explorar y comprar, pero no solicitar pagos ni publicar productos hasta que verifiques.',
    ],

    'twofa' => [
        'title' => 'Autenticación de dos factores',
        'intro' => 'Añade un segundo paso a tu inicio de sesión con una aplicación de autenticación. Aunque alguien conozca tu contraseña, no podrá entrar sin tu teléfono.',
        'admin_nudge' => 'Las cuentas de administrador deben usar siempre la autenticación de dos factores.',
        'admin_banner' => 'Tu cuenta de administrador no tiene autenticación de dos factores. Actívala para proteger el mercado.',
        'enable' => 'Activar la autenticación de dos factores',
        'setup_help' => 'Añade esta cuenta a tu aplicación de autenticación introduciendo la clave de abajo y escribe después el código de 6 dígitos que muestra la aplicación.',
        'secret' => 'Clave de configuración',
        'uri' => 'Enlace de configuración',
        'uri_help' => 'Algunas aplicaciones pueden abrir este enlace directamente. Contiene tu secreto, así que mantenlo privado.',
        'copy' => 'Copiar',
        'copied' => 'Copiado.',
        'code' => 'Código de autenticación',
        'confirm' => 'Confirmar y activar',
        'enabled' => 'Activada',
        'remaining' => 'Códigos de recuperación restantes: :count',
        'codes_title' => 'Tus códigos de recuperación',
        'codes_help' => 'Guarda estos códigos en un lugar seguro. Cada uno funciona una vez si pierdes tu teléfono. Solo se muestran ahora.',
        'saved' => 'Los he guardado',
        'regenerate_title' => 'Códigos de recuperación',
        'regenerate_help' => 'Crea un nuevo conjunto de códigos. Los anteriores dejarán de funcionar.',
        'regenerate' => 'Crear nuevos códigos',
        'regenerate_confirm' => 'Introduce tu contraseña y un código actual para crear nuevos códigos de recuperación.',
        'disable' => 'Desactivar la autenticación de dos factores',
        'disable_help' => 'Introduce tu contraseña y un código actual para desactivarla.',
    ],
];
