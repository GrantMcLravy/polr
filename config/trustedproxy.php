<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | When Polr runs behind a reverse proxy, list the proxy addresses here so
    | that the client IP (click records, GeoIP, anonymous API quotas, signup IP)
    | is taken from X-Forwarded-For. Comma-separated IPs or CIDRs, or "*" to
    | trust any proxy (only safe if Polr cannot be reached except through it).
    |
    | Default: no trusted proxies, as in Polr 2.x.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
