<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :minutes minutes.',

    // Login page (App\Livewire\Forms\LoginForm). Deliberately no lock
    // duration in the lockout message, and the same wording whether or not
    // the email belongs to an account.
    'invalid_credentials' => 'Invalid credentials. :count attempt left before this account is locked.|Invalid credentials. :count attempts left before this account is locked.',
    'locked' => 'This account is locked after too many failed attempts. Try again later or ask an Admin to unlock it.',
    'ip_throttled' => 'Too many failed login attempts from this device. Try again later or ask an Admin for help.',

];
