<?php

use App\Core\DB;
use App\Core\Session;
use App\Security\InputValidator;
include('admin_elements/admin_header.php');

$module = 'items';
$module_caption = 'Item';
$module_id = getModuleIdBySlug($module, $mysqli);
$tbl_name = DB::ITEMS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_items.php', 'WARNING', __FILE__, __LINE__);
        $action = '';
    }
}

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    $idResult = InputValidator::integer($id, 1);
    if (!$idResult['valid']) {
        $error_message = "Invalid item ID: " . $idResult['error'];
    } else {
        $itemId = $idResult['value'];
        $canDelete = has_full_access() || checkOwnership($tbl_name, $itemId, 'created_by');
        if (!$canDelete) {
            $error_message = "You do not have permission to delete this item";
            log_error("IDOR attempt: User Session::userId() tried to delete item $itemId", 'WARNING', __FILE__, __LINE__);
        } else {
            $stmt = $mysqli->prepare("DELETE FROM `" . $tbl_name . "` WHERE id=?");
            $stmt->bind_param("i", $itemId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success_message = "Item deleted successfully.";
                    flash_success($success_message);
                    header("Location:listing_$module.php");
                } else {
                    $error_message = "Could not delete record. It may have already been deleted.";
                }
            } else {
                $error_message = "Database error: " . $stmt->error;
                log_error("Delete failed for item $itemId: " . $stmt->error, 'ERROR', __FILE__, __LINE__);
            }
            $stmt->close();
        }
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6],
        ['data' => 7],
        ['data' => 8],
        ['data' => 9, 'orderable' => false, 'searchable' => false]
    ],
    'thead' => '<th width="40">SR.</th><th>NAME</th><th width="120">SELLING PRICE</th><th>SALE ACCOUNT</th><th>TAX</th><th width="120">COST PRICE</th><th>PURCHASE ACCOUNT</th><th>PREFERRED VENDOR</th><th>CREATED AT</th><th width="90">ACTION</th>',
    'page_length' => 10,
    'order' => [[0, 'desc']],
    'custom_dt_init' => true,
    'extra_js' => '
    window.HAIDatatableInitializer.init("#grid-' . $module . '", "' . $module . '", {
        stateSave: false,
        deferRender: true,
        retrieve: false,
        dom: "<\'dt-header\'<\'dt-head-left\'fl><\'dt-head-right\'>>rt<\'dt-footer\'<\'dt-foot-left\'i><\'dt-foot-right\'p>>",
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        pageLength: 10,
        ajax: {
            data: function(d) {
                d.edit_permission = ' . (granted('edit', $module_id) ? '1' : '0') . ';
                d.delete_permission = ' . (granted('delete', $module_id) ? '1' : '0') . ';
                d.session_user_id = \'' . addslashes((string)(Session::userId() ?? '')) . '\';
                d.dt_session_role_id = \'' . addslashes((string)(Session::roleId() ?? '')) . '\';
                return d;
            },
            error: function(xhr, status, error) {
                console.error("[Items] DataTable AJAX error:", error);
                console.error("[Items] Response:", xhr.responseText);
            }
        },
        columns: [
            { data: 0 },
            { data: 1 },
            { data: 2 },
            { data: 3 },
            { data: 4 },
            { data: 5 },
            { data: 6 },
            { data: 7 },
            { data: 8 },
            { data: 9, orderable: false, searchable: false }
        ],
        order: [[0, "desc"]]
    });

    $(document).off("click.haiDatatableDelete", \'[data-action="delete_record"]\');
    $(document).on("click", \'[data-action="delete_record"]\', function(e) {
        e.preventDefault();
        var id = $(this).data("id");
        var module = $(this).data("module");
        var csrfToken = $("input[name=\'csrf_token\']").val();
        if (confirm("Are you sure you want to delete this item?")) {
            var form = $("<form>", {
                "method": "POST",
                "action": "listing_' . $module . '.php"
            }).append($("<input>", {
                "type": "hidden",
                "name": "action",
                "value": "delete_" + module
            })).append($("<input>", {
                "type": "hidden",
                "name": "id",
                "value": id
            })).append($("<input>", {
                "type": "hidden",
                "name": "csrf_token",
                "value": csrfToken
            }));
            $("body").append(form);
            form.submit();
        }
    });
    '
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');


