<?php

/*
| One-click demo sign-in on the login page, for LOCAL development only.
|
| It is active only when DEMO_LOGIN=true AND APP_ENV=local. In any other environment the
| routes return 404 and the buttons are not rendered, whatever DEMO_LOGIN says. The emails
| below must be accounts created by the seeders / setup (see HANDOVER.md); no password is
| stored or needed here.
*/
return [
    'enabled' => (bool) env('DEMO_LOGIN', false),

    'accounts' => [
        'admin' => 'admin@dm.test',
        'seller' => 'demo-vendor@example.test',
        'buyer' => 'demo-buyer@example.test',
    ],
];
