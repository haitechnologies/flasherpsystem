<?php

declare(strict_types=1);

include('admin_elements/admin_header.php');

use App\Core\Container;
use App\Service\CustomerService;
use App\Exception\NotFoundException;
use App\Security\InputValidator;
use App\Security\Roles;
use App\Core\DB;

$container = Container::getInstance();
/** @var CustomerService $customerService */
$customerService = $container->get(CustomerService::class);

$module = 'customers';
$module_caption = 'Customer';
$tbl_name = DB::CUSTOMERS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

// Retrieve action and id
$action = $_REQUEST['action'] ?? $action ?? '';
$customer_id = $_REQUEST['customer_id'] ?? $_POST['customer_id'] ?? '';

// INPUT VALIDATION: Validate customer_id
$customerIdResult = InputValidator::integer($customer_id, 1);
if (!$customerIdResult['valid']) {
    flash_error('Invalid customer ID: ' . $customerIdResult['error']);
    header("Location:listing_customers.php");
    exit;
}
$customer_id = $customerIdResult['value'];

try {
    $customerObj = $customerService->getCustomer((int)$customer_id, $activeOrganizationId);
} catch (NotFoundException $e) {
    flash_error($e->getMessage());
    header("Location:listing_customers.php");
    exit;
}

// IDOR PROTECTION: Verify access permission
$module_id = getModuleIdBySlug('customers', $mysqli);
if (!granted('view', $module_id)) {
    // User doesn't have view permission, check ownership
    if ($_SESSION['h_role_id'] != Roles::SYSTEM_ADMIN) {
        $isOwner = (int)$customerObj->createdBy === (int)Session::userId() || (int)$customerObj->customerOwner === (int)Session::userId();
        if (!$isOwner) {
            flash_error('Access denied');
            header("Location:listing_customers.php");
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
if ($action == "approved" && !empty($customer_id)) {
    try {
        $customerService->approveCustomer((int)$customer_id, $activeOrganizationId, Session::userId());
        $success_message = 'This Customer is Approved.';
        flash_success($success_message);
        header("Location:customer_overview.php?customer_id=$customer_id");
        exit;
    } catch (\Throwable $e) {
        $error_message = $e->getMessage();
    }
} else if ($action == "disapproved" && !empty($customer_id)) {
    try {
        $customerService->disapproveCustomer((int)$customer_id, $activeOrganizationId, Session::userId());
        $success_message = 'This Customer is Dis-Approved.';
        flash_success($success_message);
        header("Location:customer_overview.php?customer_id=$customer_id");
        exit;
    } catch (\Throwable $e) {
        $error_message = $e->getMessage();
    }
} else if ($action == "update_opening_balance" && !empty($customer_id) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // INPUT VALIDATION: Validate opening_balance
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in customer_overview.php', 'WARNING', __FILE__, __LINE__);
    } else {
        $balanceResult = InputValidator::float($_POST['opening_balance'] ?? 0, 0, 9999999.99, 2);
        if (!$balanceResult['valid']) {
            $error_message = 'Invalid opening balance: ' . $balanceResult['error'];
        } else {
            try {
                $customerService->updateOpeningBalance((int)$customer_id, (float)$balanceResult['value'], $activeOrganizationId, Session::userId());
                $success_message = 'Opening balance has been updated successfully.';
                flash_success($success_message);
                header("Location:customer_overview.php?customer_id=$customer_id");
                exit;
            } catch (\Throwable $e) {
                $error_message = $e->getMessage();
            }
        }
    }
} else if ($action == "clone_customers" && !empty($customer_id)) {
    try {
        $newCloned = $customerService->cloneCustomer((int)$customer_id, $activeOrganizationId, Session::userId());
        $new_cloned_id = $newCloned->id;
        $success_message = 'Customer has been cloned Successfully. Please click here to view. <a href="customer_overview.php?customer_id=' . $new_cloned_id . '"> Customer ID: ' . $new_cloned_id . '</a>';
        flash_success($success_message);
        header("Location:customer_overview.php?customer_id=$customer_id");
        exit;
    } catch (\Throwable $e) {
        $error_message = $e->getMessage();
    }
} else if ($action == "mark_as_active" && !empty($customer_id)) {
    try {
        $customerService->markAsActive((int)$customer_id, $activeOrganizationId, Session::userId());
        $success_message = 'Customer has marked as Active';
        flash_success($success_message);
        header("Location:customer_overview.php?customer_id=$customer_id");
        exit;
    } catch (\Throwable $e) {
        $error_message = $e->getMessage();
    }
} else if ($action == "mark_as_inactive" && !empty($customer_id)) {
    try {
        $customerService->markAsInactive((int)$customer_id, $activeOrganizationId, Session::userId());
        $success_message = 'Customer has marked as Inactive';
        flash_success($success_message);
        header("Location:customer_overview.php?customer_id=$customer_id");
        exit;
    } catch (\Throwable $e) {
        $error_message = $e->getMessage();
    }
} else if ($action == "mark_as_primary" && !empty($contact_id) && !empty($customer_id)) {
    try {
        $customerService->markContactAsPrimary((int)$contact_id, (int)$customer_id, $activeOrganizationId);
        $success_message = 'Contact Person is Set as Primary';
        flash_success($success_message);
        header("Location:customer_overview.php?customer_id=$customer_id");
        exit;
    } catch (\Throwable $e) {
        $error_message = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| FORMAT TEMPLATE VARIABLES
|--------------------------------------------------------------------------
*/
$customer_owner             = $customerObj->customerOwner ? getTableAttr('full_name', DB::USERS, $customerObj->customerOwner) : '';
$payment_term               = $customerObj->paymentTerm ? (string)$customerObj->paymentTerm : '';
$customer_status            = $customerObj->customerStatus ? getTableAttr('value', DB::TAXONOMIES, $customerObj->customerStatus) : '';
$customer_source            = $customerObj->customerSource ? getTableAttr('value', DB::TAXONOMIES, $customerObj->customerSource) : '';
$assigned_to                = $customerObj->assignedTo ? getTableAttr('full_name', DB::USERS, $customerObj->assignedTo) : '';

$salutation                 = $customerObj->salutation ? ucwords(s__($customerObj->salutation)) : '';
$first_name                 = s__($customerObj->firstName);
$last_name                  = s__($customerObj->lastName);
$display_name               = s__($customerObj->displayName);
$address                    = s__($customerObj->address);
$email                      = s__($customerObj->email);
$phone                      = s__($customerObj->phone);
$mobile                     = s__($customerObj->mobile);

$tax_treatment              = s__($customerObj->taxTreatment);
$trn                        = s__($customerObj->trn);
$corporate_tax_number       = s__($customerObj->corporateTaxNumber);
$license_number             = s__($customerObj->licenseNumber);
$license_expiry             = s__($customerObj->licenseExpiry);
$license_expiry             = ($license_expiry == '1970-01-01' ? '' : processDateYtoD($license_expiry));

$currency                   = s__($customerObj->currency);
$exchange_rate              = s__($customerObj->exchangeRate);

$sales_person               = $customerObj->salesPerson ? getTableAttr("full_name", DB::USERS, $customerObj->salesPerson) : '';
$cs_agent                   = $customerObj->csAgent ? getTableAttr("full_name", DB::USERS, $customerObj->csAgent) : '';

$lead_category              = s__($customerObj->leadCategory);
$rating                     = s__($customerObj->rating);

$contacted_date             = s__($customerObj->contactedDate);
$contacted_date             = ($contacted_date == '1970-01-01 00:00:00' || empty($contacted_date) ? '' : dd_($contacted_date, 'd M Y g:ia'));

$description                = s__($customerObj->description);

$tags                       = s__($customerObj->tags);
$tags_arr                   = array();
$tags_captions              = '';

if ($tags != NULL) {
    $tags_arr               = explode(',', $tags);
    foreach ($tags_arr as $tag_id) {
        $tags_captions .= '<span class="badge bg-light text-dark">' . getTableAttr('value', DB::TAXONOMIES, $tag_id) . '</span> &nbsp;';
    }
}

$website                    = s__($customerObj->website);
$department                 = s__($customerObj->department);
$designation                = s__($customerObj->designation);
$x                          = s__($customerObj->x);
$facebook                   = s__($customerObj->facebook);
$instagram                  = s__($customerObj->instagram);

$approved                   = s__($customerObj->approved);
$approved_by                = s__($customerObj->approvedBy);
$approved_at                = s__($customerObj->approvedAt);

$is_active                  = s__($customerObj->isActive);
$created_at                 = s__($customerObj->createdAt);
$created_by                 = s__($customerObj->createdBy);

// Render the view template
require __DIR__ . '/views/customer_overview.view.php';