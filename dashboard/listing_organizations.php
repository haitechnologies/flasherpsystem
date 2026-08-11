<?php

use App\Core\DB;
use App\Core\Session;
use App\Security\InputValidator;
include('admin_elements/admin_header.php');

$module = 'organizations';
$module_caption = 'Organization';
$module_id = getModuleIdBySlug($module, $mysqli);
$tbl_name = DB::ORGANIZATIONS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_organizations.php', 'WARNING', __FILE__, __LINE__);
        $action = '';
    }
}

$hasDeletePermission = granted_('delete', $module) || granted('delete', $module_id);
if (($action == "delete_$module" && !empty($id)) && $hasDeletePermission) {
    $idResult = InputValidator::integer($id, 1);
    if (!$idResult['valid']) {
        $error_message = "Invalid organization ID: " . $idResult['error'];
    } else {
        $orgId = $idResult['value'];
        $canDelete = has_full_access();
        if (!$canDelete) {
            $hasCreatedByColumn = false;
            $columnStmt = $mysqli->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'created_by' LIMIT 1"
            );
            if ($columnStmt) {
                $columnStmt->bind_param('s', $tbl_name);
                if ($columnStmt->execute()) {
                    $columnResult = $columnStmt->get_result();
                    $hasCreatedByColumn = $columnResult && $columnResult->num_rows > 0;
                }
                $columnStmt->close();
            }
            if ($hasCreatedByColumn) {
                $ownerId = 0;
                $ownerStmt = $mysqli->prepare("SELECT created_by FROM `" . $tbl_name . "` WHERE id = ? LIMIT 1");
                if ($ownerStmt) {
                    $ownerStmt->bind_param('i', $orgId);
                    if ($ownerStmt->execute()) {
                        $ownerRow = $ownerStmt->get_result()->fetch_assoc();
                        $ownerId = (int)($ownerRow['created_by'] ?? 0);
                    }
                    $ownerStmt->close();
                }
                if ($ownerId > 0) {
                    $canDelete = ($ownerId === (int)Session::userId());
                } else {
                    $canDelete = granted_('delete', $module);
                }
            } else {
                $canDelete = granted_('delete', $module);
            }
        }
        if (!$canDelete) {
            $error_message = "You do not have permission to delete this organization";
            log_error("IDOR attempt: User Session::userId() tried to delete organization $orgId", 'WARNING', __FILE__, __LINE__);
        } else {
            $stmt = $mysqli->prepare("DELETE FROM `" . $tbl_name . "` WHERE id=?");
            $stmt->bind_param("i", $orgId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success_message = "Item deleted successfully.";
                    flash_success($success_message);
                    header("Location:listing_$module.php");
                    exit;
                } else {
                    $error_message = "Could not delete record. It may have already been deleted.";
                    flash_error($error_message);
                    header("Location:listing_$module.php");
                    exit;
                }
            } else {
                $error_message = "Database error: " . $stmt->error;
                log_error("Delete failed for organization $orgId: " . $stmt->error, 'ERROR', __FILE__, __LINE__);
                flash_error('Could not delete record due to a database constraint.');
                header("Location:listing_$module.php");
                exit;
            }
            $stmt->close();
        }
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'disable_add_button' => true,
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5, 'orderable' => false, 'searchable' => false]
    ],
    'thead' => '<th width="50">ID</th><th>ORGANIZATION NAME</th><th>PHONE</th><th>EMAIL</th><th width="90">CREATED AT</th><th width="90">ACTION</th>',
    'page_length' => 10,
    'order' => [[0, 'desc']],
    'dt_options' => [
        'stateSave' => false,
        'deferRender' => true,
        'retrieve' => false,
        'lengthMenu' => [[10, 25, 50, 100], [10, 25, 50, 100]],
        'ajax' => [
            'data' => [
                'edit_permission' => granted_('edit', $module) ? '1' : '0',
                'delete_permission' => granted_('delete', $module) ? '1' : '0',
            ],
        ],
    ],
    'after_card' => '<div class="alert alert-info border-0 alert-dismissible fade show">
        <span class="fw-semibold">Logo:</span> is to display on PDFs (Quotatations, Sale Orders, Invoices etc)
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');


