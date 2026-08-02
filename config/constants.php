<?php

declare(strict_types=1);

$app_name = $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'Flash Logistics';
$project_prefix = $_ENV['PROJECT_PREFIX'] ?? getenv('PROJECT_PREFIX') ?: 'flasherpsystem';
$app_domain = $_ENV['APP_DOMAIN'] ?? getenv('APP_DOMAIN') ?: 'flasherpsystem.com';

define('APP_NAME', $app_name);
define('PROJECT_PREFIX', $project_prefix);
define('APP_DOMAIN', $app_domain);

define('SUPPORT_EMAIL', $_ENV['SUPPORT_EMAIL'] ?? getenv('SUPPORT_EMAIL') ?: 'support@' . APP_DOMAIN);
define('SALES_EMAIL', $_ENV['SALES_EMAIL'] ?? getenv('SALES_EMAIL') ?: 'sales@' . APP_DOMAIN);
define('NOREPLY_EMAIL', $_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['NOREPLY_EMAIL'] ?? getenv('NOREPLY_EMAIL') ?: 'noreply@' . APP_DOMAIN);

define('SESSION_KEY_DASHBOARD', 'DASHBOARD');
define('SESSION_KEY_FRONTEND', 'FRONTEND');

define('ORGANIZATION_INVITE_EXPIRY_DAYS', 7);

define('ENTITLEMENT_CACHE_TTL', 300);

define('EMAIL_QUEUE_DEFAULT_MAX_RETRIES', 3);

define('DASHBOARD_COOKIE_SECURE', !in_array(
    strtolower((string)($_SERVER['SERVER_NAME'] ?? 'localhost')),
    ['127.0.0.1', 'localhost'],
    true
));
