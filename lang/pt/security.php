<?php

return [
    'lockout' => [
        'message' => 'Demasiadas tentativas falhadas. Tente novamente dentro de :minutes minutos.',
    ],

    'api' => [
        'already_enabled' => 'A autenticação de dois fatores já está ativada.',
        'not_started' => 'Comece primeiro a configuração da autenticação de dois fatores.',
        'invalid_code' => 'Esse código não é válido.',
        'disable_failed' => 'A palavra-passe ou o código não estão corretos.',
    ],

    'login' => [
        'code_required' => 'Introduza o código de 6 dígitos da sua aplicação de autenticação.',
    ],

    'forgot' => [
        'link' => 'Esqueceu-se da palavra-passe?',
        'title' => 'Repor a palavra-passe',
        'intro' => 'Introduza o seu e-mail e enviaremos uma ligação para escolher uma nova palavra-passe.',
        'submit' => 'Enviar ligação de reposição',
        'sent' => 'Se existir uma conta para esse e-mail, uma ligação de reposição está a caminho. Funciona durante 60 minutos.',
        'back' => 'Voltar ao início de sessão',
    ],

    'reset' => [
        'title' => 'Escolha uma nova palavra-passe',
        'intro' => 'Escolha uma nova palavra-passe. A sua sessão será terminada em todo o lado e terá de iniciar sessão novamente.',
        'password' => 'Nova palavra-passe',
        'confirm' => 'Repita a nova palavra-passe',
        'submit' => 'Guardar nova palavra-passe',
        'done' => 'A sua palavra-passe foi alterada. Inicie sessão com a nova palavra-passe.',
        'invalid_token' => 'Esta ligação de reposição é inválida ou expirou.',
        'request_new' => 'Pedir uma nova ligação',
    ],

    'verify' => [
        'banner' => 'Verifique o seu e-mail. Até lá, não pode pedir pagamentos nem publicar produtos.',
        'resend' => 'Reenviar e-mail de verificação',
        'sent' => 'E-mail de verificação enviado. Veja a sua caixa de entrada.',
        'already' => 'O seu e-mail já está verificado.',
        'done' => 'Obrigado, o seu e-mail está verificado.',
        'invalid_link' => 'Esta ligação de verificação não é válida.',
        'required' => 'Verifique primeiro o seu e-mail para fazer isto.',
    ],

    'challenge' => [
        'title' => 'Autenticação de dois fatores',
        'intro' => 'Introduza o código de 6 dígitos da sua aplicação de autenticação.',
        'recovery_intro' => 'Introduza um dos seus códigos de recuperação. Cada código funciona apenas uma vez.',
        'code' => 'Código de autenticação',
        'recovery_code' => 'Código de recuperação',
        'submit' => 'Verificar e iniciar sessão',
        'use_recovery' => 'Usar antes um código de recuperação',
        'use_code' => 'Usar antes um código de autenticação',
    ],

    'mail' => [
        'reset_subject' => 'Reponha a sua palavra-passe de :app',
        'reset_line' => 'Recebemos um pedido para repor a palavra-passe da sua conta :app.',
        'reset_button' => 'Escolher uma nova palavra-passe',
        'reset_expires' => 'Esta ligação funciona uma só vez e expira dentro de :minutes minutos.',
        'verify_subject' => 'Verifique o seu e-mail em :app',
        'verify_line' => 'Bem-vindo a :app. Confirme o seu e-mail.',
        'verify_button' => 'Verificar e-mail',
        'verify_expires' => 'Esta ligação expira dentro de :minutes minutos.',
        'ignore' => 'Se não fez este pedido, pode ignorar este e-mail.',
    ],

    'page' => [
        'title' => 'Segurança',
        'retry' => 'Tentar novamente',
        'email_title' => 'Endereço de e-mail',
        'verified' => 'Verificado',
        'unverified' => 'Não verificado',
        'unverified_help' => 'Pode navegar e comprar, mas não pode pedir pagamentos nem publicar produtos até verificar.',
    ],

    'twofa' => [
        'title' => 'Autenticação de dois fatores',
        'intro' => 'Adicione um segundo passo ao seu início de sessão com uma aplicação de autenticação. Mesmo que alguém saiba a sua palavra-passe, não consegue entrar sem o seu telemóvel.',
        'admin_nudge' => 'As contas de administrador devem usar sempre a autenticação de dois fatores.',
        'admin_banner' => 'A sua conta de administrador não tem autenticação de dois fatores. Ative-a para proteger o mercado.',
        'enable' => 'Ativar a autenticação de dois fatores',
        'setup_help' => 'Adicione esta conta à sua aplicação de autenticação introduzindo a chave abaixo e depois escreva o código de 6 dígitos mostrado pela aplicação.',
        'secret' => 'Chave de configuração',
        'uri' => 'Ligação de configuração',
        'uri_help' => 'Algumas aplicações abrem esta ligação diretamente. Contém o seu segredo, por isso mantenha-a privada.',
        'copy' => 'Copiar',
        'copied' => 'Copiado.',
        'code' => 'Código de autenticação',
        'confirm' => 'Confirmar e ativar',
        'enabled' => 'Ativa',
        'remaining' => 'Códigos de recuperação restantes: :count',
        'codes_title' => 'Os seus códigos de recuperação',
        'codes_help' => 'Guarde estes códigos num local seguro. Cada um funciona uma vez se perder o telemóvel. São mostrados apenas agora.',
        'saved' => 'Já os guardei',
        'regenerate_title' => 'Códigos de recuperação',
        'regenerate_help' => 'Crie um novo conjunto de códigos. Os antigos deixam de funcionar.',
        'regenerate' => 'Criar novos códigos',
        'regenerate_confirm' => 'Introduza a sua palavra-passe e um código atual para criar novos códigos de recuperação.',
        'disable' => 'Desativar a autenticação de dois fatores',
        'disable_help' => 'Introduza a sua palavra-passe e um código atual para a desativar.',
    ],
];
