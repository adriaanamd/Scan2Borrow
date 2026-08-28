<?php
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value, " \t\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, $default = null)
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_NAME', env('DB_NAME', 'scan2borrow_2.0'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

define('LOAN_DAYS',          (int) env('LOAN_DAYS', 7));      
define('FINE_PER_DAY',       (float) env('FINE_PER_DAY', 5)); 
define('MAX_BOOKS_PER_USER', (int) env('MAX_BOOKS_PER_USER', 3));
define('REQUIRE_APPROVAL',   (bool) env('REQUIRE_APPROVAL', true));
define('TEACHER_MAX_DAYS',   (int) env('TEACHER_MAX_DAYS', 30));

define('MAIL_HOST',     env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT',     (int) env('MAIL_PORT', 587));
define('MAIL_USERNAME', env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', env('MAIL_PASSWORD', ''));
define('MAIL_FROM',     env('MAIL_FROM', 'jenmargvargas@gmail.com'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Scan2Borrow Library Management'));

// SMS Configuration (using TextBee)
define('SMS_ENABLED',    env('SMS_ENABLED', true));
define('SMS_PROVIDER',   env('SMS_PROVIDER', 'textbee'));
define('SMS_API_KEY',    env('SMS_API_KEY', '559d8a3f-0139-49e2-b8db-6ed034323c53'));
define('SMS_DEVICE_ID',  env('SMS_DEVICE_ID', '6a3e5c4877015dcde17076d0'));
define('SMS_SENDER',     env('SMS_SENDER', ''));

define('APP_NAME', 'Scan2Borrow');
define('APP_TZ',   env('APP_TZ', 'Asia/Manila'));
date_default_timezone_set(APP_TZ);
