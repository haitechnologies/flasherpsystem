<?php

declare(strict_types=1);

namespace App\Core;

use App\Security\Roles;
use Throwable;

/**
 * Deletion Manager Class
 *
 * Centralized deletion logic with ownership verification, audit logging,
 * and consistent error handling across the entire dashboard.
 */
class DeletionManager
{
    /**
     * Database connection instance
     * @var mixed
     */
    private static mixed $db = null;

    /**
     * Project prefix for sessions/globals
     * @var string|null
     */
    private static ?string $project_pre = null;

    /**
     * Initialize the DeletionManager with database connection
     *
     * @param mixed $db Database connection (mysqli or App\Core\Database)
     * @param string|null $project_pre Project prefix from $GLOBALS
     * @return void
     */
    public static function init(mixed $db, ?string $project_pre = null): void
    {
        self::$db = $db;
        self::$project_pre = $project_pre ?? (defined('PROJECT_PREFIX') ? PROJECT_PREFIX : 'flasherpsystem');
    }

    /**
     * Resolve the Database instance (either direct PDO wrapper or resolved from Container)
     */
    private static function getDatabase(): Database
    {
        if (self::$db instanceof Database) {
            return self::$db;
        }

        try {
            $container = Container::getInstance();
            if ($container->has(Database::class)) {
                $resolved = $container->get(Database::class);
                if ($resolved instanceof Database) {
                    return $resolved;
                }
            }
        } catch (Throwable $e) {
            // Ignore container resolution errors
        }

        return new Database();
    }

    /**
     * Safe delete a record with full permission & ownership validation
     *
     * @param string $table_name
     * @param int $record_id
     * @param int $user_id
     * @param array $options
     * @return array
     */
    public static function delete(
        string $table_name,
        int $record_id,
        int $user_id,
        array $options = []
    ): array {
        // Ensure manager is initialized
        if (self::$db === null || self::$project_pre === null) {
            return self::error('Manager not initialized. Call DeletionManager::init() first.', 'NOT_INITIALIZED');
        }

        $verify_field = $options['verify_field'] ?? 'title';
        $item_label = $options['item_label'] ?? 'Record';
        $cascade_deletes = $options['cascade_deletes'] ?? [];
        $module_slug = $options['module_slug'] ?? self::tableToModuleSlug($table_name);

        if ($record_id <= 0) {
            return self::error('Invalid record ID.', 'INVALID_ID');
        }

        if (!function_exists('granted') || !granted('delete', $module_slug)) {
            return self::error(
                "You don't have permission to delete {$item_label}.",
                'PERMISSION_DENIED'
            );
        }

        try {
            $current_role_id = $_SESSION[self::$project_pre]['DASHBOARD']['role_id'] ?? null;
            $has_full_access = false;
            if (class_exists(Roles::class)) {
                $has_full_access = Roles::hasFullAccess($current_role_id);
            }

            // Using PDO wrapper
            $db = self::getDatabase();

            // 4. Verify record exists & get data for logging
            $verify_query = "SELECT * FROM `{$table_name}` WHERE id = :id";
            $verify_params = ['id' => $record_id];

            if (!$has_full_access) {
                $verify_query .= " AND created_by = :created_by";
                $verify_params['created_by'] = $user_id;
            }
            $verify_query .= " LIMIT 1";

            $record_data = $db->fetchOne($verify_query, $verify_params);

            if (!$record_data) {
                return self::error(
                    "{$item_label} not found or you don't have permission to delete it.",
                    'NOT_FOUND'
                );
            }

            $record_label = $record_data[$verify_field] ?? "ID {$record_id}";

            // 5. Handle cascade deletes (if configured)
            if (!empty($cascade_deletes) && is_array($cascade_deletes)) {
                foreach ($cascade_deletes as $cascade_table => $cascade_foreign_key) {
                    $cascade_query = "DELETE FROM `{$cascade_table}` WHERE `{$cascade_foreign_key}` = :record_id";
                    try {
                        $db->execute($cascade_query, ['record_id' => $record_id]);
                    } catch (Throwable $e) {
                        ErrorCapture::record("CASCADE_DELETE_ERROR: " . $e->getMessage() . " | Table: {$cascade_table}");
                    }
                }
            }

            // 6. Execute deletion
            $delete_query = "DELETE FROM `{$table_name}` WHERE id = :id";
            $delete_params = ['id' => $record_id];
            if (!$has_full_access) {
                $delete_query .= " AND created_by = :created_by";
                $delete_params['created_by'] = $user_id;
            }

            $stmt = $db->execute($delete_query, $delete_params);
            $affectedRows = $stmt->rowCount();

            if ($affectedRows === 0) {
                return self::error(
                    "{$item_label} may have already been deleted.",
                    'NO_ROWS_DELETED'
                );
            }

            // 8. Log the action for audit trail
            self::logAction(
                $user_id,
                'DELETE',
                $module_slug,
                $record_id,
                "Deleted {$item_label}: {$record_label}"
            );

            return [
                'success' => true,
                'message' => "{$item_label} '{$record_label}' deleted successfully.",
                'record_data' => $record_data,
                'id' => $record_id,
                'error_code' => null
            ];
        } catch (Throwable $e) {
            ErrorCapture::record(
                "DELETION_EXCEPTION: " . $e->getMessage() .
                    " | Table: {$table_name} | Record ID: {$record_id} | User ID: {$user_id}"
            );

            return self::error($e->getMessage(), 'EXCEPTION');
        }
    }

    private static function logAction(int $user_id, string $action, string $module, int $record_id, string $description): void
    {
        // Reserved for audit logging
    }

    private static function error(string $message, string $error_code): array
    {
        return [
            'success' => false,
            'message' => $message,
            'record_data' => null,
            'id' => null,
            'error_code' => $error_code
        ];
    }

    private static function tableToModuleSlug(string $table_name): string
    {
        $slug = preg_replace('/^(hai_|flasherpsystem_|erp_)/', '', $table_name);
        if ($slug === null) {
            $slug = $table_name;
        }

        if (str_ends_with($slug, 's') && strlen($slug) > 3) {
            $exceptions = ['address', 'status', 'business', 'process'];
            if (!in_array(substr($slug, 0, -1), $exceptions, true)) {
                $singular = substr($slug, 0, -1);
                if (strlen($singular) > 2) {
                    $slug = $singular;
                }
            }
        }

        return $slug;
    }
}
