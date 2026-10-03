<?php

return [
    'lockout' => [
        'message' => '失败次数过多，请 :minutes 分钟后再试。',
    ],

    'api' => [
        'already_enabled' => '双重验证已开启。',
        'not_started' => '请先开始设置双重验证。',
        'invalid_code' => '该验证码无效。',
        'disable_failed' => '密码或验证码不正确。',
    ],

    'login' => [
        'code_required' => '请输入验证器应用中的 6 位验证码。',
    ],

    'forgot' => [
        'link' => '忘记密码？',
        'title' => '重置密码',
        'intro' => '输入你的邮箱地址，我们会发送一个链接让你设置新密码。',
        'submit' => '发送重置链接',
        'sent' => '如果该邮箱对应一个账户，重置链接已在发送途中。链接有效期 60 分钟。',
        'back' => '返回登录',
    ],

    'reset' => [
        'title' => '设置新密码',
        'intro' => '请设置新密码。你将在所有设备上退出登录，需要重新登录。',
        'password' => '新密码',
        'confirm' => '再次输入新密码',
        'submit' => '保存新密码',
        'done' => '你的密码已更改。请使用新密码登录。',
        'invalid_token' => '此重置链接无效或已过期。',
        'request_new' => '重新获取链接',
    ],

    'verify' => [
        'banner' => '请验证你的邮箱地址。在此之前，你无法申请提现或发布商品。',
        'resend' => '重新发送验证邮件',
        'sent' => '验证邮件已发送，请查看收件箱。',
        'already' => '你的邮箱地址已验证。',
        'done' => '谢谢，你的邮箱地址已验证。',
        'invalid_link' => '此验证链接无效。',
        'required' => '请先验证邮箱地址才能执行此操作。',
    ],

    'challenge' => [
        'title' => '双重验证',
        'intro' => '请输入验证器应用中的 6 位验证码。',
        'recovery_intro' => '请输入一个恢复码。每个恢复码只能使用一次。',
        'code' => '验证码',
        'recovery_code' => '恢复码',
        'submit' => '验证并登录',
        'use_recovery' => '改用恢复码',
        'use_code' => '改用验证码',
    ],

    'mail' => [
        'reset_subject' => '重置你的 :app 密码',
        'reset_line' => '我们收到了重置你的 :app 账户密码的请求。',
        'reset_button' => '设置新密码',
        'reset_expires' => '此链接只能使用一次，:minutes 分钟后失效。',
        'verify_subject' => '验证你在 :app 的邮箱地址',
        'verify_line' => '欢迎来到 :app。请确认你的邮箱地址。',
        'verify_button' => '验证邮箱地址',
        'verify_expires' => '此链接将在 :minutes 分钟后失效。',
        'ignore' => '如果这不是你本人的操作，请忽略此邮件。',
    ],

    'page' => [
        'title' => '安全',
        'retry' => '重试',
        'email_title' => '邮箱地址',
        'verified' => '已验证',
        'unverified' => '未验证',
        'unverified_help' => '你可以浏览和购买，但在验证之前无法申请提现或发布商品。',
    ],

    'twofa' => [
        'title' => '双重验证',
        'intro' => '使用验证器应用为登录增加第二步。即使有人知道你的密码，没有你的手机也无法登录。',
        'admin_nudge' => '管理员账户应始终启用双重验证。',
        'admin_banner' => '你的管理员账户尚未启用双重验证。请开启以保护平台。',
        'enable' => '开启双重验证',
        'setup_help' => '在验证器应用中输入下方密钥以添加此账户，然后输入应用显示的 6 位验证码。',
        'secret' => '设置密钥',
        'uri' => '设置链接',
        'uri_help' => '部分应用可直接打开此链接。它包含你的密钥，请妥善保密。',
        'copy' => '复制',
        'copied' => '已复制。',
        'code' => '验证码',
        'confirm' => '确认并开启',
        'enabled' => '已开启',
        'remaining' => '剩余恢复码：:count',
        'codes_title' => '你的恢复码',
        'codes_help' => '请将这些恢复码保存在安全的地方。丢失手机时，每个恢复码可使用一次。它们仅在此时显示。',
        'saved' => '我已保存',
        'regenerate_title' => '恢复码',
        'regenerate_help' => '生成一组新的恢复码，旧的将失效。',
        'regenerate' => '生成新恢复码',
        'regenerate_confirm' => '输入密码和当前验证码以生成新的恢复码。',
        'disable' => '关闭双重验证',
        'disable_help' => '输入密码和当前验证码以关闭。',
    ],
];
