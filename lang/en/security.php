<?php

return [
    'lockout' => [
        'message' => 'Too many failed attempts. Try again in :minutes minutes.',
    ],

    'api' => [
        'already_enabled' => 'Two-factor authentication is already on.',
        'not_started' => 'Start the two-factor setup first.',
        'invalid_code' => 'That code is not valid.',
        'disable_failed' => 'The password or code is not correct.',
    ],

    'login' => [
        'code_required' => 'Enter the 6-digit code from your authenticator app.',
    ],

    'forgot' => [
        'link' => 'Forgot your password?',
        'title' => 'Reset your password',
        'intro' => 'Enter your email address and we will send you a link to choose a new password.',
        'submit' => 'Send reset link',
        'sent' => 'If an account exists for that email, a reset link is on its way. It works for 60 minutes.',
        'back' => 'Back to log in',
    ],

    'reset' => [
        'title' => 'Choose a new password',
        'intro' => 'Pick a new password. You will be logged out everywhere and must log in again.',
        'password' => 'New password',
        'confirm' => 'Repeat the new password',
        'submit' => 'Save new password',
        'done' => 'Your password was changed. Log in with your new password.',
        'invalid_token' => 'This reset link is invalid or has expired.',
        'request_new' => 'Request a new link',
    ],

    'verify' => [
        'banner' => 'Please verify your email address. Until then you cannot request payouts or publish products.',
        'resend' => 'Resend verification email',
        'sent' => 'Verification email sent. Check your inbox.',
        'already' => 'Your email address is already verified.',
        'done' => 'Thank you, your email address is verified.',
        'invalid_link' => 'This verification link is not valid.',
        'required' => 'Verify your email address first to do this.',
    ],

    'challenge' => [
        'title' => 'Two-factor authentication',
        'intro' => 'Enter the 6-digit code from your authenticator app.',
        'recovery_intro' => 'Enter one of your recovery codes. Each code works only once.',
        'code' => 'Authentication code',
        'recovery_code' => 'Recovery code',
        'submit' => 'Verify and log in',
        'use_recovery' => 'Use a recovery code instead',
        'use_code' => 'Use an authentication code instead',
    ],

    'mail' => [
        'reset_subject' => 'Reset your :app password',
        'reset_line' => 'We received a request to reset the password for your :app account.',
        'reset_button' => 'Choose a new password',
        'reset_expires' => 'This link works once and expires in :minutes minutes.',
        'verify_subject' => 'Verify your email address for :app',
        'verify_line' => 'Welcome to :app. Please confirm your email address.',
        'verify_button' => 'Verify email address',
        'verify_expires' => 'This link expires in :minutes minutes.',
        'ignore' => 'If you did not ask for this, you can ignore this email.',
    ],

    'page' => [
        'title' => 'Security',
        'retry' => 'Try again',
        'email_title' => 'Email address',
        'verified' => 'Verified',
        'unverified' => 'Not verified',
        'unverified_help' => 'You can browse and buy, but you cannot request payouts or publish products until you verify.',
    ],

    'twofa' => [
        'title' => 'Two-factor authentication',
        'intro' => 'Add a second step to your login with an authenticator app. Even if someone learns your password, they cannot get in without your phone.',
        'admin_nudge' => 'Administrator accounts should always use two-factor authentication.',
        'admin_banner' => 'Your administrator account has no two-factor authentication. Turn it on to protect the marketplace.',
        'enable' => 'Turn on two-factor authentication',
        'setup_help' => 'Add this account to your authenticator app by entering the key below, then type the 6-digit code the app shows.',
        'secret' => 'Setup key',
        'uri' => 'Setup link',
        'uri_help' => 'Some apps can open this link directly. It contains your secret, so keep it private.',
        'copy' => 'Copy',
        'copied' => 'Copied.',
        'code' => 'Authentication code',
        'confirm' => 'Confirm and turn on',
        'enabled' => 'On',
        'remaining' => 'Recovery codes left: :count',
        'codes_title' => 'Your recovery codes',
        'codes_help' => 'Save these codes somewhere safe. Each works once if you lose your phone. They are shown only now.',
        'saved' => 'I have saved them',
        'regenerate_title' => 'Recovery codes',
        'regenerate_help' => 'Make a new set of codes. The old ones stop working.',
        'regenerate' => 'Create new codes',
        'regenerate_confirm' => 'Enter your password and a current code to create new recovery codes.',
        'disable' => 'Turn off two-factor authentication',
        'disable_help' => 'Enter your password and a current code to turn it off.',
    ],
];
