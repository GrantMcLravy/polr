<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password hashing
    |--------------------------------------------------------------------------
    |
    | Polr 2.x stored bcrypt hashes (cost 10); they keep verifying as-is. New
    | hashes use Laravel's default cost. "verify" is off so that a stored value
    | that is not a bcrypt hash fails the login instead of throwing, as before.
    |
    */

    'driver' => env('HASH_DRIVER', 'bcrypt'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', false),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', false),
    ],

    'rehash_on_login' => false,

];
