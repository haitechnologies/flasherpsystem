<?php

if (!function_exists('flasherpsystemSessionIsProduction')) {
    function flasherpsystemSessionIsProduction(): bool
    {
        $appEnv = getenv('APP_ENV') ?: '';
        $serverName = $_SERVER['SERVER_NAME'] ?? '';

        return $appEnv === 'production'
            || ($serverName !== '' && !in_array($serverName, ['127.0.0.1', 'localhost'], true));
    }
}

if (!function_exists('flasherpsystemSessionBasePath')) {
    function flasherpsystemSessionBasePath(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = rtrim(dirname($scriptName), '/');
        $basePath = preg_replace('#/dashboard(?:/.*)?$#', '', $scriptDir);

        if (!is_string($basePath) || $basePath === '' || $basePath === '.') {
            return '/';
        }

        return $basePath;
    }
}

if (!function_exists('flasherpsystemSessionCookiePath')) {
    function flasherpsystemSessionCookiePath(string $scope): string
    {
        $basePath = flasherpsystemSessionBasePath();

        if ($scope === 'dashboard') {
            return $basePath === '/' ? '/dashboard' : rtrim($basePath, '/') . '/dashboard';
        }

        return $basePath;
    }
}

if (!function_exists('startScopedSession')) {
    function startScopedSession(string $scope): void
    {
        $normalizedScope = strtolower($scope) === 'dashboard' ? 'dashboard' : 'frontend';

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        $isProduction = flasherpsystemSessionIsProduction();

        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure', $isProduction ? '1' : '0');

        $sess_prefix = strtoupper(defined('PROJECT_PREFIX') ? PROJECT_PREFIX : 'flasherpsystem');
        session_name($normalizedScope === 'dashboard' ? $sess_prefix . '_DASHBOARD_SESSID' : $sess_prefix . '_FRONTEND_SESSID');
        session_set_cookie_params(0, flasherpsystemSessionCookiePath($normalizedScope), '', $isProduction, true);
        session_start();
    }
}

if (!function_exists('startFrontendSession')) {
    function startFrontendSession(): void
    {
        startScopedSession('frontend');
    }
}

if (!function_exists('startDashboardSession')) {
    function startDashboardSession(): void
    {
        startScopedSession('dashboard');
    }
}

if (!function_exists('clearFrontendAuthSession')) {
    function clearFrontendAuthSession(?string $projectPrefix = null): void
    {
        unset(
            $_SESSION['frontend_user_id'],
            $_SESSION['frontend_user_email'],
            $_SESSION['frontend_user_name'],
            $_SESSION['frontend_login_time'],
            $_SESSION['remember_me']
        );

        if ($projectPrefix !== null && isset($_SESSION[$projectPrefix]['FRONTEND'])) {
            unset($_SESSION[$projectPrefix]['FRONTEND']);
            if (empty($_SESSION[$projectPrefix])) {
                unset($_SESSION[$projectPrefix]);
            }
        }

        if (isset($_SESSION['project_pre']['FRONTEND'])) {
            unset($_SESSION['project_pre']['FRONTEND']);
            if (empty($_SESSION['project_pre'])) {
                unset($_SESSION['project_pre']);
            }
        }
    }
}

if (!function_exists('clearDashboardAuthSession')) {
    function clearDashboardAuthSession(?string $projectPrefix = null): void
    {
        if ($projectPrefix !== null && isset($_SESSION[$projectPrefix]['DASHBOARD'])) {
            unset($_SESSION[$projectPrefix]['DASHBOARD']);
            if (empty($_SESSION[$projectPrefix])) {
                unset($_SESSION[$projectPrefix]);
            }
        }

        unset($_SESSION['csrf_token']);
    }
}
