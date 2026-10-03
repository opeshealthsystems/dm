<?php

/*
| Supported interface languages. Keys are the Laravel locale codes (and lang/ folder names).
| 'dir' drives <html dir>; Arabic is right-to-left.
*/
return [
    'default' => 'en',

    'supported' => [
        'en' => ['name' => 'English', 'dir' => 'ltr'],
        'nl' => ['name' => 'Nederlands', 'dir' => 'ltr'],
        'de' => ['name' => 'Deutsch', 'dir' => 'ltr'],
        'fr' => ['name' => 'Français', 'dir' => 'ltr'],
        'es' => ['name' => 'Español', 'dir' => 'ltr'],
        'it' => ['name' => 'Italiano', 'dir' => 'ltr'],
        'pt' => ['name' => 'Português', 'dir' => 'ltr'],
        'ru' => ['name' => 'Русский', 'dir' => 'ltr'],
        'zh-hans' => ['name' => '简体中文', 'dir' => 'ltr'],
        'ja' => ['name' => '日本語', 'dir' => 'ltr'],
        'ko' => ['name' => '한국어', 'dir' => 'ltr'],
        'ar' => ['name' => 'العربية', 'dir' => 'rtl'],
    ],
];
