<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static function dashboard(): array
    {
        $pre = defined('PROJECT_PREFIX') ? PROJECT_PREFIX : 'flasherpsystem';
        return $_SESSION[$pre]['DASHBOARD'] ?? [];
    }

    public static function userId(): int
    {
        return (int)(self::dashboard()['user_id'] ?? 0);
    }

    public static function roleId(): int
    {
        return (int)(self::dashboard()['role_id'] ?? 0);
    }

    public static function orgId(): int
    {
        return (int)(self::dashboard()['organization_id'] ?? 0);
    }

    public static function userName(): string
    {
        return (string)(self::dashboard()['user_name'] ?? '');
    }

    public static function email(): string
    {
        return (string)(self::dashboard()['email'] ?? '');
    }

    public static function fullName(): string
    {
        return (string)(self::dashboard()['full_name'] ?? '');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::dashboard()[$key] ?? $default;
    }
}
