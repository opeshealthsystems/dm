<?php

/*
| Sidebar navigation per area. 'label' is a translation key under lang/<locale>/common.php
| (nav.*). Every entry must have a route in routes/web.php; a test checks this.
*/
return [
    'buyer' => [
        ['label' => 'nav.orders', 'route' => 'buyer.orders'],
        ['label' => 'nav.cart', 'route' => 'buyer.cart'],
        ['label' => 'nav.messages', 'route' => 'buyer.messages'],
        ['label' => 'nav.following', 'route' => 'buyer.following'],
        ['label' => 'nav.profile', 'route' => 'buyer.profile'],
    ],
    'seller' => [
        ['label' => 'nav.overview', 'route' => 'seller.overview'],
        ['label' => 'nav.orders', 'route' => 'seller.orders'],
        ['label' => 'nav.products', 'route' => 'seller.products'],
        ['label' => 'nav.wallet', 'route' => 'seller.wallet'],
        ['label' => 'nav.reviews', 'route' => 'seller.reviews'],
        ['label' => 'nav.messages', 'route' => 'seller.messages'],
        ['label' => 'nav.developers', 'route' => 'seller.developers'],
        ['label' => 'nav.profile', 'route' => 'seller.profile'],
    ],
    'admin' => [
        ['label' => 'nav.overview', 'route' => 'admin.overview'],
        ['label' => 'nav.users', 'route' => 'admin.users'],
        ['label' => 'nav.catalog', 'route' => 'admin.catalog'],
        ['label' => 'nav.orders', 'route' => 'admin.orders'],
        ['label' => 'nav.disputes', 'route' => 'admin.disputes'],
        ['label' => 'nav.payouts', 'route' => 'admin.payouts'],
        ['label' => 'nav.settings', 'route' => 'admin.settings'],
        ['label' => 'nav.audit', 'route' => 'admin.audit'],
    ],
];
