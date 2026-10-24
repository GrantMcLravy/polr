<?php

/*
|--------------------------------------------------------------------------
| Polr settings
|--------------------------------------------------------------------------
|
| Every Polr setting, read from the same environment variables (.env) that
| Polr 2.x used, so existing .env files and the setup wizard keep working.
| Application code reads these with config('polr.<key>') instead of env(),
| which makes `php artisan config:cache` safe. After editing .env, re-run
| config:cache (or config:clear) if the configuration is cached.
|
| Keys are the variable names in lower case (leading underscores dropped).
|
*/

return [

    // Application
    'app_name' => env('APP_NAME'),
    'app_protocol' => env('APP_PROTOCOL'),
    'app_address' => env('APP_ADDRESS'),
    'app_stylesheet' => env('APP_STYLESHEET'),

    // Set by the setup wizard
    'setup_ran' => env('POLR_SETUP_RAN'),
    'generated_at' => env('POLR_GENERATED_AT'),
    'tmp_setup_auth_key' => env('TMP_SETUP_AUTH_KEY'),

    // Version information (POLR_*: written at setup; VERSION*: from .env.setup,
    // shown by the setup page before setup has run)
    'polr_version' => env('POLR_VERSION'),
    'polr_reldate' => env('POLR_RELDATE'),
    'version' => env('VERSION'),
    'version_relmonth' => env('VERSION_RELMONTH'),
    'version_relday' => env('VERSION_RELDAY'),
    'version_relyear' => env('VERSION_RELYEAR'),

    // Interface and links
    'setting_public_interface' => env('SETTING_PUBLIC_INTERFACE'),
    'setting_shorten_permission' => env('SETTING_SHORTEN_PERMISSION'),
    'setting_index_redirect' => env('SETTING_INDEX_REDIRECT'),
    'setting_redirect_404' => env('SETTING_REDIRECT_404'),
    'setting_pseudorandom_ending' => env('SETTING_PSEUDORANDOM_ENDING'),
    'polr_base' => env('POLR_BASE'),
    'polr_secret_bytes' => env('POLR_SECRET_BYTES'),
    'pseudo_random_key_length' => env('_PSEUDO_RANDOM_KEY_LENGTH'),

    // Accounts
    'polr_allow_acct_creation' => env('POLR_ALLOW_ACCT_CREATION'),
    'polr_acct_activation' => env('POLR_ACCT_ACTIVATION'),
    'polr_acct_creation_recaptcha' => env('POLR_ACCT_CREATION_RECAPTCHA'),
    'polr_recaptcha_site_key' => env('POLR_RECAPTCHA_SITE_KEY'),
    'polr_recaptcha_secret_key' => env('POLR_RECAPTCHA_SECRET_KEY'),
    'setting_password_recov' => env('SETTING_PASSWORD_RECOV'),
    'setting_restrict_email_domain' => env('SETTING_RESTRICT_EMAIL_DOMAIN'),
    'setting_allowed_email_domains' => env('SETTING_ALLOWED_EMAIL_DOMAINS'),

    // API
    'setting_auto_api' => env('SETTING_AUTO_API'),
    'setting_anon_api' => env('SETTING_ANON_API'),
    'setting_anon_api_quota' => env('SETTING_ANON_API_QUOTA'),
    'api_key_length' => env('_API_KEY_LENGTH'),

    // Analytics
    'setting_adv_analytics' => env('SETTING_ADV_ANALYTICS'),
    'analytics_max_days_diff' => env('_ANALYTICS_MAX_DAYS_DIFF'),

];
