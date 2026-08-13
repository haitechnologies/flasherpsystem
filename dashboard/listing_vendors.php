<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;
use App\Core\Container;
use App\Service\VendorService;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'vendors';
$module_caption = 'Vendor';
$tbl_name = DB::VENDORS;
$module_id = getModuleIdBySlug($module, $mysqli);
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_vendors.php', 'WARNING', __FILE__, __LINE__);
        $action = '';
    }
}

$container = Container::getInstance();
$vendorService = $container->get(VendorService::class);

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    try {
        $vendorObj = $vendorService->getVendor((int)$id, $activeOrganizationId);

        $canDelete = has_full_access() || (int)$vendorObj->createdBy === (int)Session::userId() || (int)$vendorObj->vendorOwner === (int)Session::userId();

        if (!$canDelete) {
            $error_message = "You do not have permission to delete this vendor";
            log_error("IDOR attempt: User Session::userId() tried to delete vendor $id", 'WARNING', __FILE__, __LINE__);
            flash_error($error_message);
        } else {
            $vendorService->deleteVendor((int)$id, $activeOrganizationId);
            $success_message = "Vendor deleted successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php");
            exit;
        }
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'vendors', 'action' => 'delete', 'vendor_id' => (int)($id ?? 0)]);
        flash_error($error_message);
    } catch (\Throwable $e) {
        $error_message = "An error occurred while deleting the vendor: " . $e->getMessage();
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'vendors', 'action' => 'delete', 'vendor_id' => (int)($id ?? 0)]);
        flash_error($error_message);
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th>NAME</th>
        <th>COMPANY</th>
        <th>EMAIL</th>
        <th>WORK PHONE</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
    ],
    'order' => [[0, 'asc']],
    'page_length' => 25,
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
