<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Container;
use App\Core\Database;
use App\Core\DB;
use Exception;
use ReflectionClass;

/**
 * Role Constants Class
 * Centralized role ID definitions and helper methods
 */
class Roles
{
    /**
     * ROLE ID CONSTANTS
     */
    public const SYSTEM_ADMIN = 1;
    public const SUPER_ADMIN = 2;
    public const SALES = 3;
    public const OPERATIONS = 4;
    public const ACCOUNTS = 5;

    /**
     * Maps role IDs to display names
     *
     * @var array<int, string>
     */
    private static array $role_names = [
        self::SYSTEM_ADMIN => 'System Admin',
        self::SUPER_ADMIN => 'Super Admin',
        self::SALES => 'Sales',
        self::OPERATIONS => 'Operations',
        self::ACCOUNTS => 'Accounts'
    ];

    /**
     * Cache for database-loaded roles (populated on first access)
     *
     * @var array<int, string>|null
     */
    private static ?array $database_roles = null;

    /**
     * Get role name by ID
     * First checks static constants, then falls back to database
     *
     * @param int|null $role_id The role ID
     * @return string The role name or 'Unknown Role' if not found
     */
    public static function getName(?int $role_id): string
    {
        if ($role_id === null) {
            return 'Unknown Role';
        }

        // First check static role names
        if (isset(self::$role_names[$role_id])) {
            return self::$role_names[$role_id];
        }

        // Fall back to database for dynamic roles
        self::loadDatabaseRoles();

        return self::$database_roles[$role_id] ?? 'Unknown Role';
    }

    /**
     * Get all role names (both static and from database)
     *
     * @return array<int, string> Associative array of role_id => role_name
     */
    public static function getAllNames(): array
    {
        self::loadDatabaseRoles();

        // Merge static and database roles
        return array_merge(self::$role_names, self::$database_roles ?? []);
    }

    /**
     * Load roles from database (for roles not defined as constants)
     */
    private static function loadDatabaseRoles(): void
    {
        // Only load once per request
        if (self::$database_roles !== null) {
            return;
        }

        self::$database_roles = [];

        try {
            $container = Container::getInstance();
            if (!$container->has(Database::class)) {
                return;
            }

            $db = $container->get(Database::class);

            // Get all roles from database that aren't already in static array
            $static_ids = array_keys(self::$role_names);

            $placeholders = [];
            $params = [];
            foreach ($static_ids as $index => $id) {
                $placeholder = 'id_' . $index;
                $placeholders[] = ':' . $placeholder;
                $params[$placeholder] = $id;
            }

            $placeholdersStr = implode(',', $placeholders);

            $sql = "SELECT id, role_name 
                    FROM " . DB::ROLES . " 
                    WHERE id NOT IN ($placeholdersStr) 
                    AND is_active = 1";

            $rows = $db->fetchAll($sql, $params);

            foreach ($rows as $row) {
                self::$database_roles[(int)$row['id']] = (string)$row['role_name'];
            }
        } catch (\Throwable $e) {
            // Silently fail - database roles are optional
            self::$database_roles = [];
        }
    }

    /**
     * Check if a role ID has full system access
     *
     * @param int|null $role_id The role ID to check
     * @return bool True if role has full access, false otherwise
     */
    public static function hasFullAccess(?int $role_id): bool
    {
        if ($role_id === null) {
            return false;
        }
        return in_array($role_id, [self::SYSTEM_ADMIN, self::SUPER_ADMIN], true);
    }

    /**
     * Validate if a role ID exists (in constants or database)
     *
     * @param int|null $role_id The role ID to validate
     * @return bool True if valid role ID, false otherwise
     */
    public static function isValid(?int $role_id): bool
    {
        if ($role_id === null) {
            return false;
        }

        // Check static roles first
        if (isset(self::$role_names[$role_id])) {
            return true;
        }

        // Check database roles
        self::loadDatabaseRoles();

        return isset(self::$database_roles[$role_id]);
    }

    /**
     * Check if role is System Admin
     */
    public static function isSystemAdmin(?int $role_id): bool
    {
        return $role_id === self::SYSTEM_ADMIN;
    }

    /**
     * Check if role is Super Admin
     */
    public static function isSuperAdmin(?int $role_id): bool
    {
        return $role_id === self::SUPER_ADMIN;
    }

    /**
     * Check if role is Sales
     */
    public static function isSales(?int $role_id): bool
    {
        return $role_id === self::SALES;
    }

    /**
     * Check if role is Operations
     */
    public static function isOperations(?int $role_id): bool
    {
        return $role_id === self::OPERATIONS;
    }

    /**
     * Check if role is Accounts
     */
    public static function isAccounts(?int $role_id): bool
    {
        return $role_id === self::ACCOUNTS;
    }

    /**
     * Get current user's role ID from session
     *
     * @param string|null $project_prefix Optional project prefix
     * @return int|null Role ID or null if not logged in
     */
    public static function getCurrentRoleId(?string $project_prefix = null): ?int
    {
        if ($project_prefix === null) {
            global $project_pre;
            $project_prefix = $project_pre ?? 'flasherpsystem';
        }

        $roleId = $_SESSION[$project_prefix]['DASHBOARD']['role_id'] ?? null;
        return $roleId !== null ? (int)$roleId : null;
    }

    /**
     * Check if current user has full access
     */
    public static function currentUserHasFullAccess(): bool
    {
        $role_id = self::getCurrentRoleId();
        return $role_id !== null && self::hasFullAccess($role_id);
    }

    /**
     * Require specific role(s) to access current page
     * If user doesn't have required role, shows 403 forbidden page and exits
     *
     * @param int|array $required_roles Single role ID or array of allowed role IDs
     * @param string|null $message Optional custom error message
     * @param bool $log Whether to log unauthorized access
     * @return void
     */
    public static function requireRole(int|array $required_roles, ?string $message = null, bool $log = true): void
    {
        global $project_pre;
        $project_prefix = $project_pre ?? 'flasherpsystem';

        // Get current user's role
        $current_role_id = $_SESSION[$project_prefix]['DASHBOARD']['role_id'] ?? null;

        // If no role in session, redirect to login
        if ($current_role_id === null) {
            header("Location: login.php?session_expired=1");
            exit;
        }

        $current_role_id = (int)$current_role_id;

        // Normalize to array
        $required_roles = is_array($required_roles) ? $required_roles : [$required_roles];

        // Check if user has required role
        if (!in_array($current_role_id, $required_roles, true)) {
            // Log unauthorized access attempt
            if ($log && function_exists('log_error')) {
                $required_role_names = array_map(function ($rid) {
                    return self::getName((int)$rid);
                }, $required_roles);

                log_error(
                    'Unauthorized page access attempt',
                    'WARNING',
                    $_SERVER['PHP_SELF'] ?? 'unknown',
                    0,
                    [
                        'user_id' => $_SESSION[$project_prefix]['DASHBOARD']['user_id'] ?? 'unknown',
                        'user_role' => self::getName($current_role_id),
                        'required_roles' => implode(', ', $required_role_names),
                        'requested_page' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]
                );
            }

            // Show 403 forbidden page
            self::showForbiddenPage($message, $required_roles);
            exit;
        }
    }

    /**
     * Require System Admin role
     */
    public static function requireSystemAdmin(?string $message = null): void
    {
        self::requireRole(self::SYSTEM_ADMIN, $message ?? 'Only System Admin has access to this page');
    }

    /**
     * Require Super Admin role
     */
    public static function requireSuperAdmin(?string $message = null): void
    {
        self::requireRole(self::SUPER_ADMIN, $message ?? 'Only Super Admin has access to this page');
    }

    /**
     * Require System Admin OR Super Admin role
     */
    public static function requireAdminAccess(?string $message = null): void
    {
        self::requireRole(
            [self::SYSTEM_ADMIN, self::SUPER_ADMIN],
            $message ?? 'Only administrators have access to this page'
        );
    }

    /**
     * Require full access (System Admin or Super Admin)
     */
    public static function requireFullAccess(?string $message = null): void
    {
        global $project_pre;
        $project_prefix = $project_pre ?? 'flasherpsystem';

        $current_role_id = $_SESSION[$project_prefix]['DASHBOARD']['role_id'] ?? null;

        if ($current_role_id === null || !self::hasFullAccess((int)$current_role_id)) {
            self::showForbiddenPage($message ?? 'Administrator access required');
            exit;
        }
    }

    /**
     * Check if current user has specific role(s)
     *
     * @param int|array $roles Single role ID or array of role IDs
     * @return bool
     */
    public static function currentUserHasRole(int|array $roles): bool
    {
        global $project_pre;
        $project_prefix = $project_pre ?? 'flasherpsystem';

        $current_role_id = $_SESSION[$project_prefix]['DASHBOARD']['role_id'] ?? null;

        if ($current_role_id === null) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];

        return in_array((int)$current_role_id, $roles, true);
    }

    /**
     * Show 403 Forbidden page
     */
    private static function showForbiddenPage(?string $message = null, ?array $required_roles = null): void
    {
        $forbidden_page = __DIR__ . '/../../dashboard/admin_elements/403_forbidden.php';

        if (file_exists($forbidden_page)) {
            $error_message = $message;
            $required_role_ids = $required_roles;
            include($forbidden_page);
        } else {
            global $project_pre;
            $project_prefix = $project_pre ?? 'flasherpsystem';

            http_response_code(403);

            $current_role = self::getName((int)($_SESSION[$project_prefix]['DASHBOARD']['role_id'] ?? 0));

            $required_text = '';
            if ($required_roles !== null) {
                $role_names = array_map(function ($rid) {
                    return self::getName((int)$rid);
                }, $required_roles);
                $required_text = implode(' or ', $role_names);
            }

            if (!$message) {
                $message = $required_text
                    ? "You need $required_text access to view this page"
                    : 'You do not have permission to access this page';
            }

            echo '<!DOCTYPE html>';
            echo '<html lang="en">';
            echo '<head>';
            echo '<meta charset="UTF-8">';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
            echo '<title>403 - Access Forbidden</title>';
            echo '<style>';
            echo 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }';
            echo '.error-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }';
            echo '.error-code { font-size: 72px; font-weight: 700; color: #dc3545; margin: 0; }';
            echo 'h1 { font-size: 24px; margin: 20px 0 10px; color: #333; }';
            echo 'p { color: #666; margin: 10px 0; }';
            echo '.btn { display: inline-block; padding: 10px 24px; margin: 20px 5px 0; background: #0d6efd; color: white; text-decoration: none; border-radius: 4px; }';
            echo '</style>';
            echo '</head>';
            echo '<body>';
            echo '<div class="error-container">';
            echo '<p class="error-code">403</p>';
            echo '<h1>Access Forbidden</h1>';
            echo '<p>' . htmlspecialchars($message) . '</p>';
            if ($required_text) {
                echo '<p style="font-size: 14px; margin-top: 20px;">';
                echo '<strong>Required:</strong> ' . htmlspecialchars($required_text) . '<br>';
                echo '<strong>Your Role:</strong> ' . htmlspecialchars($current_role);
                echo '</p>';
            }
            echo '<a href="index.php" class="btn">Go to Dashboard</a>';
            echo '<a href="javascript:history.back()" class="btn" style="background: #6c757d;">Go Back</a>';
            echo '</div>';
            echo '</body>';
            echo '</html>';
        }
        exit;
    }
}
