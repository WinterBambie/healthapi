<?php

function env(string $key, string $default = ''): string {
    return $_ENV[$key]
        ?? (getenv($key) !== false ? getenv($key) : $default);
}

// ── App ───────────────────────────────────────────────────────────────────────
define('APP_URL',        env('APP_URL',        'http://localhost'));
define('FRONTEND_URL',   env('FRONTEND_URL',   'https://softyhealth.vercel.app'));
define('JWT_SECRET',     env('JWT_SECRET',     'clave_local_dev_cambiar_en_prod'));

// ── Mailer ────────────────────────────────────────────────────────────────────
define('MAIL_HOST',      env('MAIL_HOST',      'smtp.gmail.com'));
define('MAIL_PORT',      env('MAIL_PORT',      '587'));
define('MAIL_USER',      env('MAIL_USER',      ''));
define('MAIL_PASS',      env('MAIL_PASS',      ''));
define('MAIL_FROM',      env('MAIL_FROM',      ''));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'SoftyHealth'));