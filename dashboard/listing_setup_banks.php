<?php

use App\Core\DB;
use App\Service\SetupBankService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'setup_banks';
$module_caption = 'Banks (Institutions)';
$module_id = getModuleIdBySlug($module, $mysqli);
$tbl_name = DB::SETUP_BANKS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$bankService = $container->get(SetupBankService::class);

// CSRF TOKEN VALIDATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_setup_banks.php', 'WARNING', __FILE__, __LINE__);
        $action = '';
    }
}

// DELETE
if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    try {
        if (!has_full_access()) {
            $model = $bankService->getById((int)$id, (int)$activeOrganizationId);
            if ($model && $model->createdBy !== (int)Session::userId()) {
                $error_message = "You do not have permission to delete this bank institution";
                log_error("IDOR attempt: User Session::userId() tried to delete bank institution $id", 'WARNING', __FILE__, __LINE__);
            }
        }
        if (empty($error_message)) {
            $bankService->delete((int)$id, (int)$activeOrganizationId);
            $success_message = "Item deleted successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php");
            exit;
        }
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
    } catch (\Throwable $e) {
        $error_message = "An error occurred while deleting the record.";
        log_error("Delete failed for bank institution $id: " . $e->getMessage(), 'ERROR', __FILE__, __LINE__);
    }
}

$editPerm = granted('edit', $module_id) ? '1' : '0';
$deletePerm = granted('delete', $module_id) ? '1' : '0';

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="40">SR.</th>
        <th>INSTITUTION NAME</th>
        <th>HEAD OFFICE</th>
        <th width="80">STATUS</th>
        <th width="90">ACTION</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4, 'orderable' => false, 'searchable' => false],
    ],
    'order' => [[1, 'asc']],
    'page_length' => 25,
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
