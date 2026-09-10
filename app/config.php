<?php
declare(strict_types=1);
return [
    'name' => 'Krunal Pawar',
    'email' => getenv('CONTACT_EMAIL') ?: 'kmpawar0004@gmail.com',
    'phone' => '+91 63517 16007',
    'phone_uri' => '+916351716007',
    'linkedin' => 'https://www.linkedin.com/in/krunalmpawar',
    'origin' => rtrim(getenv('APP_URL') ?: 'http://portfolio.test', '/'),
    'storage' => getenv('STORAGE_PATH') ?: dirname(__DIR__, 2) . '/portfolio-storage',
    'mail_enabled' => getenv('MAIL_ENABLED') === 'true',
    'mail_from' => getenv('MAIL_FROM') ?: '',
    'admin_hash' => getenv('ADMIN_PASSWORD_HASH') ?: '',
];
