<?php

declare(strict_types=1);
/**
 * Shared listing page handler for publish/unpublish/delete actions.
 *
 * Required variables (must be defined before include):
 *   $module            — module slug, e.g. 'banks'
 *   $module_caption    — human-readable label, e.g. 'Bank'
 *   $tbl_name          — database table name, e.g. DB::BANKS
 *   $module_id         — numeric module ID for permission checks
 *
 * Optional variables:
 *   $error_message     — defaults to ''
 *   $success_message   — defaults to ''
 *   $handler_config    — array with overrides:
 *     'hard_delete'         (bool) default false — use DELETE FROM instead of soft-delete
 *     'ownership_check'     (bool) default false — enforce created_by = Session::userId() for non-superadmins
 *     'redirect_on_success' (bool) default false — redirect on success instead of inline message
 */

use App\Core\DB;
use App\Core\FlashMessage;
use App\Core\Session;

$error_message = $error_message ?? '';
$success_message = $success_message ?? '';
$handler_config = $handler_config ?? [];

$hard_delete = (bool)($handler_config['hard_delete'] ?? false);
$ownership_check = (bool)($handler_config['ownership_check'] ?? false);
$redirect_on_success = (bool)($handler_config['redirect_on_success'] ?? false);

$db = DB::pdo();

// Publish
if ($action === "publish_{$module}" && !empty($id) && granted('edit', $module_id)) {
    $db->execute("UPDATE `{$tbl_name}` SET is_active = 1 WHERE id = :id", ['id' => $id]);
    $success_message = "{$module_caption} Published Successfully.";

// Unpublish
} elseif ($action === "unpublish_{$module}" && !empty($id) && granted('edit', $module_id)) {
    $db->execute("UPDATE `{$tbl_name}` SET is_active = 0 WHERE id = :id", ['id' => $id]);
    $success_message = "{$module_caption} Un-Published Successfully.";

// Delete
} elseif ($action === "delete_{$module}" && !empty($id) && granted('delete', $module_id)) {
    try {
        if ($ownership_check && !is_SuperAdmin()) {
            $owned = $db->fetchOne(
                "SELECT id FROM `{$tbl_name}` WHERE id = :id AND created_by = :uid",
                ['id' => $id, 'uid' => Session::userId()]
            );
            if (!$owned) {
                $error_message = "Action denied. You are not authorized to delete this record.";
            }
        }

        if (empty($error_message)) {
            if ($hard_delete) {
                $db->execute("DELETE FROM `{$tbl_name}` WHERE id = :id", ['id' => $id]);
            } else {
                $db->execute("UPDATE `{$tbl_name}` SET is_active = 0 WHERE id = :id", ['id' => $id]);
            }
            $success_message = "{$module_caption} Deleted Successfully.";

            if ($redirect_on_success) {
                FlashMessage::success($success_message);
                header("Location: listing_{$module}.php");
                exit;
            }
        }
    } catch (\Throwable $e) {
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => $module, 'action' => 'delete', 'id' => (int)($id ?? 0)]);
        $error_message = "{$module_caption} could not be deleted.";
    }
}
