<?php

use App\Core\DB;
use App\Service\SetupStatusService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'setup_statuses';
$module_caption = 'Status';
$module_id = getModuleIdBySlug($module, $mysqli);
$tbl_name = DB::TAXONOMIES;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$statusService = $container->get(SetupStatusService::class);

// CSRF TOKEN VALIDATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_setup_statuses.php', 'WARNING', __FILE__, __LINE__);
        $action = '';
    }
}

// DELETE
if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    try {
        if (!has_full_access()) {
            $model = $statusService->getById((int)$id);
            if ($model && $model->createdBy !== (int)Session::userId()) {
                $error_message = "You do not have permission to delete this status";
                log_error("IDOR attempt: User Session::userId() tried to delete status $id", 'WARNING', __FILE__, __LINE__);
            }
        }
        if (empty($error_message)) {
            $statusService->delete((int)$id);
            $success_message = "Item deleted successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php");
            exit;
        }
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'setup_statuses', 'action' => 'delete']);
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'setup_statuses', 'action' => 'delete']);
    } catch (\Throwable $e) {
        $error_message = "An error occurred while deleting the record.";
        log_error("Delete failed for status $id: " . $e->getMessage(), 'ERROR', __FILE__, __LINE__);
    }
}

$editPerm = granted('edit', $module_id) ? '1' : '0';
$deletePerm = granted('delete', $module_id) ? '1' : '0';

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="40">SR.</th>
        <th>STATUS</th>
        <th>STATUS TYPE</th>
        <th width="90">CREATED AT</th>
        <th width="50">STATUS</th>
        <th width="90">ACTION</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5, 'orderable' => false, 'searchable' => false],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 10,
    'extra_js' => "
        $(document).on('click', '[data-action=\"delete_record\"]', function(e) {
            e.preventDefault();
            if (confirm('Delete this item?')) {
                var csrfToken = $('input[name=\"csrf_token\"]').val();
                var form = $('<form>', { 'method': 'POST', 'action': 'listing_" . $module . ".php' })
                    .append($('<input>', { 'type': 'hidden', 'name': 'action', 'value': 'delete_" . $module . "' }))
                    .append($('<input>', { 'type': 'hidden', 'name': 'id', 'value': $(this).data('id') }))
                    .append($('<input>', { 'type': 'hidden', 'name': 'csrf_token', 'value': csrfToken }));
                $('body').append(form);
                form.submit();
            }
        });
    ",
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
