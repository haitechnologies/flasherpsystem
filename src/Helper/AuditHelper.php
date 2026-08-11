<?php

declare(strict_types=1);

namespace App\Helper;
use App\Core\ErrorCapture;

/**
 * Audit Helper Class
 * Provides consistent audit column population across all database operations
 *
 * Professional Standards:
 * - Auto-populate created_by, created_at on INSERT
 * - Auto-populate updated_by, updated_at on UPDATE
 * - Ensure all changes are tracked with user accountability
 */
class AuditHelper
{
    /**
     * Add audit columns for INSERT operations
     *
     * @param array $data Reference to data array being inserted
     * @param int $user_id User ID performing the creation
     * @return void
     */
    public static function setCreatedAudit(array &$data, int $user_id): void
    {
        $data['created_by'] = $user_id;
        $data['created_at'] = date('Y-m-d H:i:s');

        // Also set updated fields on creation
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['updated_by'] = $user_id;
    }

    /**
     * Add audit columns for UPDATE operations
     *
     * @param array $data Reference to data array being updated
     * @param int $user_id User ID performing the update
     * @return void
     */
    public static function setUpdatedAudit(array &$data, int $user_id): void
    {
        $data['updated_by'] = $user_id;
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    /**
     * Get current user ID from session
     *
     * @param string|null $project_prefix Project prefix (e.g., 'uaehscodes')
     * @return int|null User ID or null if not logged in
     */
    public static function getCurrentUserId(?string $project_prefix = null): ?int
    {
        if ($project_prefix === null) {
            $project_prefix = defined('PROJECT_PREFIX') ? PROJECT_PREFIX : '';
        }

        if ($project_prefix === '') {
            return null;
        }

        $userId = $_SESSION[$project_prefix]['DASHBOARD']['user_id'] ?? null;
        return $userId !== null ? (int)$userId : null;
    }

    /**
     * Add audit columns with auto-detection of current user
     * Uses global $project_pre to get user from session
     *
     * @param array $data Reference to data array
     * @param bool $is_insert True for INSERT, false for UPDATE
     * @return bool Success status
     */
    public static function setAudit(array &$data, bool $is_insert = false): bool
    {
        $user_id = self::getCurrentUserId();

        if ($user_id === null) {
            // Log warning but don't fail
            ErrorCapture::record('AuditHelper: No user ID found in session for audit column');
            return false;
        }

        if ($is_insert) {
            self::setCreatedAudit($data, $user_id);
        } else {
            self::setUpdatedAudit($data, $user_id);
        }

        return true;
    }

    /**
     * Soft delete - set deleted_at and deleted_by
     * (For future use when soft delete is implemented)
     *
     * @param array $data Reference to data array
     * @param int $user_id User ID performing the deletion
     * @return void
     */
    public static function setDeletedAudit(array &$data, int $user_id): void
    {
        $data['deleted_by'] = $user_id;
        $data['deleted_at'] = date('Y-m-d H:i:s');
    }
}
