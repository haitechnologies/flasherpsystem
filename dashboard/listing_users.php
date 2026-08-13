<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;
use App\Core\Container;
use App\Service\UserService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$container = Container::getInstance();
$userService = $container->get(UserService::class);

$module = 'users';
$module_caption = 'Employees';
$tbl_name = DB::USERS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$handler_config = ['hard_delete' => false, 'ownership_check' => false, 'redirect_on_success' => false];
include('admin_elements/listing_handler.php');

if (($action == "delete_$module" && !empty($id))) {
    try {
        $user = $userService->getById((int)$id);
        $photo = $user->photo;
        $userService->delete((int)$id);

        if (!empty($photo)) {
            delete_photo($photo, $photo_upload_path, '1');
            delete_photo($photo, $photo_upload_path, '0');
        }
        $success_message = "Employee account deleted successfully.";
        flash_success($success_message);
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'users', 'action' => 'delete']);
        flash_error($error_message);
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'users', 'action' => 'delete']);
        flash_error($error_message);
    } catch (\Throwable $e) {
        $error_message = "Unable to delete employee account. Please try again.";
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'users', 'action' => 'delete']);
        flash_error($error_message);
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="50" class="col-center">#</th>
        <th>NAME</th>
        <th width="110">DATE JOINED</th>
        <th>ROLE</th>
        <th width="80" class="col-center">STATUS</th>
        <th width="120">AIR TICKET</th>
        <th width="90" class="col-center">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'orderable' => false, 'searchable' => false],
        ['data' => 1],
        ['data' => 2, 'orderable' => true, 'searchable' => false],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5, 'orderable' => true, 'searchable' => false],
        ['data' => 6, 'orderable' => false, 'searchable' => false],
    ],
    'order' => [[1, 'asc']],
    'page_length' => 10,
    'search_placeholder' => 'Search employees...',
    'extra_js' => "
        $(document).on('click', '[data-action=\"delete_record\"]', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var module = $(this).data('module');
            if (confirm('Are you sure you want to delete this record?')) {
                var form = $('<form>', { 'method': 'POST', 'action': 'listing_{$module}.php' })
                    .append($('<input>', { 'type': 'hidden', 'name': 'action', 'value': 'delete_' + module }))
                    .append($('<input>', { 'type': 'hidden', 'name': 'id', 'value': id }));
                $('body').append(form);
                form.submit();
            }
        });
    ",
];



include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
