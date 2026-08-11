<?php

declare(strict_types=1);

include('admin_elements/admin_header.php');

use App\Core\Container;
use App\Core\Session;
use App\Service\VendorService;
use App\Exception\NotFoundException;
use App\Security\InputValidator;
use App\Security\Roles;
use App\Core\DB;

$container = Container::getInstance();
/** @var VendorService $vendorService */
$vendorService = $container->get(VendorService::class);

$module = 'vendors';
$module_caption = 'Vendor';
$tbl_name = DB::VENDORS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

// Retrieve action and id
$action = $_REQUEST['action'] ?? $action ?? '';
$vendor_id = $_REQUEST['vendor_id'] ?? $_POST['vendor_id'] ?? '';

// INPUT VALIDATION: Validate vendor_id
$vendorIdResult = InputValidator::integer($vendor_id, 1);
if (!$vendorIdResult['valid']) {
    flash_error('Invalid vendor ID: ' . $vendorIdResult['error']);
    header("Location:listing_vendors.php");
    exit;
}
$vendor_id = $vendorIdResult['value'];

try {
    $vendorObj = $vendorService->getVendor((int)$vendor_id, $activeOrganizationId);
} catch (NotFoundException $e) {
    flash_error($e->getMessage());
    header("Location:listing_vendors.php");
    exit;
}

// IDOR PROTECTION: Verify access permission
$module_id = getModuleIdBySlug('vendors', $mysqli);
if (!granted('view', $module_id)) {
    // User doesn't have view permission, check ownership
    if ($_SESSION['h_role_id'] != Roles::SYSTEM_ADMIN) {
        $isOwner = (int)$vendorObj->createdBy === (int)Session::userId() || (int)$vendorObj->vendorOwner === (int)Session::userId();
        if (!$isOwner) {
            flash_error('Access denied');
            header("Location:listing_vendors.php");
            exit;
        }
    }
}

$contact_id = 0;
if (isset($_REQUEST['contact_id']))        $contact_id     = $_REQUEST['contact_id'];
if (isset($_POST['contact_id']))           $contact_id     = $_POST['contact_id'];

// INPUT VALIDATION: Validate contact_id if provided
if (!empty($contact_id)) {
    $contactIdResult = InputValidator::integer($contact_id, 1);
    if ($contactIdResult['valid']) {
        $contact_id = $contactIdResult['value'];
    } else {
        $contact_id = 0;
    }
}

/*
|--------------------------------------------------------------------------
| ACTION HANDLING
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_error('Invalid security token. Please refresh the page and try again.');
        log_error('CSRF token validation failed in vendor_overview.php', 'WARNING', __FILE__, __LINE__);
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action == "approved" && !empty($vendor_id)) {
    if (!Roles::hasFullAccess(Session::roleId()) && !Roles::isAccounts(Session::roleId())) {
        flash_error('Only Accounts or Admin can approve vendors.');
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    } else {
        try {
            $vendorService->approveVendor((int)$vendor_id, $activeOrganizationId, Session::userId());
            $success_message = 'This Vendor is Approved.';
            updateVendorLogs((int)$vendor_id, 'vendor', 'approved', (int)$vendor_id);
            flash_success($success_message);
            header("Location:vendor_overview.php?vendor_id=$vendor_id");
            exit;
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            header("Location:vendor_overview.php?vendor_id=$vendor_id");
            exit;
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action == "disapproved" && !empty($vendor_id)) {
    if (!Roles::hasFullAccess(Session::roleId()) && !Roles::isAccounts(Session::roleId())) {
        flash_error('Only Accounts or Admin can disapprove vendors.');
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    } else {
        try {
            $vendorService->disapproveVendor((int)$vendor_id, $activeOrganizationId, Session::userId());
            $success_message = 'This Vendor is Dis-Approved.';
            updateVendorLogs((int)$vendor_id, 'vendor', 'disapproved', (int)$vendor_id);
            flash_success($success_message);
            header("Location:vendor_overview.php?vendor_id=$vendor_id");
            exit;
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            header("Location:vendor_overview.php?vendor_id=$vendor_id");
            exit;
        }
    }
} else if ($action == "update_opening_balance" && !empty($vendor_id) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $balanceResult = InputValidator::float($_POST['opening_balance'] ?? 0, 0, 9999999.99, 2);
    if (!$balanceResult['valid']) {
        flash_error('Invalid opening balance: ' . $balanceResult['error']);
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    } else {
        try {
            $vendorService->updateOpeningBalance((int)$vendor_id, (float)$balanceResult['value'], $activeOrganizationId, Session::userId());
            $success_message = 'Opening balance has been updated successfully.';
            updateVendorLogs((int)$vendor_id, 'vendor', 'opening_balance', (int)$vendor_id);
            flash_success($success_message);
            header("Location:vendor_overview.php?vendor_id=$vendor_id");
            exit;
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            header("Location:vendor_overview.php?vendor_id=$vendor_id");
            exit;
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action == "clone_vendors" && !empty($vendor_id)) {
    try {
        $newCloned = $vendorService->cloneVendor((int)$vendor_id, $activeOrganizationId, Session::userId());
        $new_cloned_id = $newCloned->id;
        $success_message = 'Vendor has been cloned successfully. Vendor ID: ' . $new_cloned_id;
        updateVendorLogs((int)$vendor_id, 'vendor', 'clone', $new_cloned_id);
        flash_success($success_message);
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    } catch (\Throwable $e) {
        flash_error($e->getMessage());
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action == "mark_as_active" && !empty($vendor_id)) {
    try {
        $vendorService->markAsActive((int)$vendor_id, $activeOrganizationId, Session::userId());
        $success_message = 'Vendor has marked as Active';
        updateVendorLogs((int)$vendor_id, 'vendor', 'active', (int)$vendor_id);
        flash_success($success_message);
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    } catch (\Throwable $e) {
        flash_error($e->getMessage());
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action == "mark_as_inactive" && !empty($vendor_id)) {
    try {
        $vendorService->markAsInactive((int)$vendor_id, $activeOrganizationId, Session::userId());
        $success_message = 'Vendor has marked as Inactive';
        updateVendorLogs((int)$vendor_id, 'vendor', 'inactive', (int)$vendor_id);
        flash_success($success_message);
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    } catch (\Throwable $e) {
        flash_error($e->getMessage());
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action == "mark_as_primary" && !empty($contact_id) && !empty($vendor_id)) {
    try {
        $vendorService->markContactAsPrimary((int)$contact_id, (int)$vendor_id, $activeOrganizationId);
        $success_message = 'Contact Person is Set as Primary';
        updateVendorLogs((int)$vendor_id, 'contact', 'primary', (int)$contact_id);
        flash_success($success_message);
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    } catch (\Throwable $e) {
        flash_error($e->getMessage());
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| FORMAT TEMPLATE VARIABLES
|--------------------------------------------------------------------------
*/
$vendor_owner             = $vendorObj->vendorOwner ? getTableAttr('full_name', DB::USERS, $vendorObj->vendorOwner) : '';
$payment_term             = $vendorObj->paymentTerm ? (string)$vendorObj->paymentTerm : '';
$vendor_status            = $vendorObj->vendorStatus ? getTableAttr('value', DB::TAXONOMIES, $vendorObj->vendorStatus) : '';
$vendor_source            = $vendorObj->vendorSource ? getTableAttr('value', DB::TAXONOMIES, $vendorObj->vendorSource) : '';
$assigned_to              = $vendorObj->assignedTo ? getTableAttr('full_name', DB::USERS, $vendorObj->assignedTo) : '';

$salutation               = $vendorObj->salutation ? ucwords(s__($vendorObj->salutation)) : '';
$first_name               = s__($vendorObj->firstName);
$last_name                = s__($vendorObj->lastName);
$display_name             = s__($vendorObj->displayName);
$address                  = s__($vendorObj->address);
$email                    = s__($vendorObj->email);
$phone                    = s__($vendorObj->phone);
$mobile                   = s__($vendorObj->mobile);

$tax_treatment            = s__($vendorObj->taxTreatment);
$trn                      = s__($vendorObj->trn);
$corporate_tax_number     = s__($vendorObj->corporateTaxNumber);
$license_number           = s__($vendorObj->licenseNumber);
$license_expiry           = s__($vendorObj->licenseExpiry);
$license_expiry           = ($license_expiry == '1970-01-01' || empty($license_expiry) ? '' : ddm_($license_expiry));

$currency                 = s__($vendorObj->currency);
$exchange_rate            = s__($vendorObj->exchangeRate);

$sales_person             = $vendorObj->salesPerson ? getTableAttr("full_name", DB::USERS, $vendorObj->salesPerson) : '';
$cs_agent                 = $vendorObj->csAgent ? getTableAttr("department", DB::DEPARTMENTS, $vendorObj->csAgent) : '';

$lead_category            = s__($vendorObj->leadCategory);
$rating                   = s__($vendorObj->rating);

$contacted_date           = s__($vendorObj->contactedDate);
$contacted_date           = ($contacted_date == '1970-01-01 00:00:00' || empty($contacted_date) ? '' : ddm_($contacted_date));

$description              = s__($vendorObj->description);

$tags                     = s__($vendorObj->tags);
$tags_arr                 = array();
$tags_captions            = '';

if ($tags != NULL) {
    $tags_arr             = explode(',', $tags);
    foreach ($tags_arr as $tag_id) {
        $tags_captions .= '<span class="badge bg-light text-dark">' . getTableAttr('value', DB::TAXONOMIES, $tag_id) . '</span> &nbsp;';
    }
}

$website                  = s__($vendorObj->website);
$department               = s__($vendorObj->department);
$designation              = s__($vendorObj->designation);
$x                        = s__($vendorObj->x);
$facebook                 = s__($vendorObj->facebook);
$instagram                = s__($vendorObj->instagram);

$approved                 = s__($vendorObj->approved);
$approved_by              = s__($vendorObj->approvedBy);
$approved_at              = s__($vendorObj->approvedAt);

$is_active                = s__($vendorObj->isActive);
$created_at               = s__($vendorObj->createdAt);
$created_by               = s__($vendorObj->createdBy);

$timelineEntries = $vendorService->getActivityTimeline((int)$vendor_id, $activeOrganizationId);

// Render the view template
require __DIR__ . '/views/vendor_overview.view.php';
