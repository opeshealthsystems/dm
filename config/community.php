<?php

/*
| Community forum settings. Every limit is here so tests and operators can change it.
*/
return [
    'edit_window_minutes' => 15,
    // An account must be at least this old before its first post.
    'cooldown_minutes' => 5,
    // Accounts younger than this are "new" and get the link limit below.
    'new_account_hours' => 24,
    'new_account_max_links' => 2,

    'title_max' => 150,
    'body_max' => 10000,
    'per_page' => 20,

    // Posting limits per user (threads and replies share the bucket).
    'rate_per_minute' => 5,
    'rate_per_hour' => 40,
    'report_per_minute' => 10,
];
