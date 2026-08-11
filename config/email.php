<?php

declare(strict_types=1);

/**
 * Email SMTP credentials — centralised.
 *
 * SECURITY NOTE: No secrets are hard-coded here. All values are loaded from
 * the environment (.env is git-ignored; secrets stay out of version control).
 * Override any value by setting the corresponding .env variable.
 *
 * The active outbound account is `system@flasherpsystem.com` (Titan Email).
 */

return [

    // Provider mode: "database" reads live accounts from `erp_email_providers`.
    // Fall back to the static SMTP values below when no row is matched.
    'provider' => $_ENV['MAIL_PROVIDER'] ?? 'database',

    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'system@flasherpsystem.com',
        'name'    => $_ENV['MAIL_FROM_NAME'] ?? 'Flash ERP',
    ],

    'smtp' => [
        'host'       => $_ENV['MAIL_SMTP_HOST'] ?? 'smtp.titan.email',
        'port'       => (int) ($_ENV['MAIL_SMTP_PORT'] ?? '465'),
        'encryption' => $_ENV['MAIL_SMTP_ENCRYPTION'] ?? 'ssl',
        'username'   => $_ENV['MAIL_SMTP_USERNAME'] ?? 'system@flasherpsystem.com',
        'password'   => $_ENV['MAIL_SMTP_PASSWORD'] ?? '',
    ],

];
