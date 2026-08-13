<?php


use App\Core\DB;
use App\Security\Roles;
use App\Security\InputValidator;
include('admin_elements/admin_header.php');

$module             = 'payments_received';
$module_caption     = 'Payment';
$tbl_name             = $tbl_prefix . $module;
$error_message         = '';
$success_message     = '';
$hide_add_button = true;


/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();


/*
|--------------------------------------------------------------------------
| CSRF TOKEN VALIDATION
|--------------------------------------------------------------------------
| Validate CSRF token for all POST requests involving actions
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_customer_payments.php', 'WARNING', __FILE__, __LINE__);
        $_POST['action'] = '';
    }
}


if (isset($_REQUEST['customer_id']) && !empty($_REQUEST['customer_id'])) {
    $customer_id     = e_s__($_REQUEST['customer_id']);
} else {
    $customer_id = 0;
}


/*
|--------------------------------------------------------------------------
| 	PAGINATION
|--------------------------------------------------------------------------
|
*/

$limit              = 25;
$stages             = 2;


if (isset($_GET['page_no']) && !empty($_GET['page_no'])) {
    $page_no            = e_s__($_GET['page_no']);
} else {
    $page_no            = 1;
}

$targetpage = 'listing_customer_payments.php?customer_id=' . $customer_id;


if ($page_no) {
    $start   = ($page_no - 1) * $limit;
} else {
    $start   = 0;
}


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
|
*/
if (($action == "delete_$module" && !empty($id))) {
    // CSRF: enforce a valid token for the delete action (GET or POST)
    $csrfCheck = validate_csrf_token($_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '');
    if (!$csrfCheck) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_customer_payments.php delete action', 'WARNING', __FILE__, __LINE__);
    } elseif (!granted('delete', $module_id)) {
        $error_message = 'You do not have permission to delete this payment';
        log_error("IDOR attempt: User tried to delete payment in listing_customer_payments.php without delete permission", 'WARNING', __FILE__, __LINE__);
    } else {
        // INPUT VALIDATION: Validate payment ID
        $idResult = InputValidator::integer($id, 1);
        if (!$idResult['valid']) {
            $error_message = "Invalid payment ID: " . $idResult['error'];
        } else {
            $validPaymentId = $idResult['value'];
            try {
                $paymentService = \App\Core\Container::getInstance()->get(\App\Service\PaymentReceivedService::class);

                // Check ownership if not full access
                if (!Roles::hasFullAccess($session_role_id)) {
                    $payment = $paymentService->getPayment($validPaymentId, $activeOrganizationId);
                    if ($payment->createdBy !== (int)\App\Core\Session::userId()) {
                        throw new \Exception("You do not have permission to delete this payment");
                    }
                }

                if ($paymentService->deletePayment($validPaymentId, $activeOrganizationId)) {
                    $success_message = "$module_caption Deleted Successfully.";
                    flash_success($success_message);
                    header("Location:listing_customer_payments.php?customer_id=$customer_id");
                    exit;
                } else {
                    $error_message = "Sorry! $module Could Not Be Deleted.";
                }
            } catch (\Throwable $e) {
                $error_message = $e->getMessage();
                log_error("Delete failed for payment $validPaymentId: " . $e->getMessage(), 'ERROR', __FILE__, __LINE__);
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/

$db = \App\Core\Container::getInstance()->get(\App\Core\Database::class);

//COUNT QUERY
$countRow = $db->fetchOne(
    "SELECT COUNT(id) as cnt FROM `{DB::PAYMENTS_RECEIVED}` WHERE customer_id = :customer_id AND organization_id = :org_id AND is_active = 1",
    ['customer_id' => $customer_id, 'org_id' => $activeOrganizationId]
);
$total_pages = (int)($countRow['cnt'] ?? 0);

//NORMAL QUERY
$result_customer_payments = $db->fetchAll(
    "SELECT id, payment_no, customer_id, payment_status, total_amount_received, bank_charges,
            payment_date, payment_method, deposit_to, reference_no, publish, is_active, created_at
     FROM `{DB::PAYMENTS_RECEIVED}`
     WHERE customer_id = :customer_id AND organization_id = :org_id AND is_active = 1
     ORDER BY id DESC LIMIT :start, :limit",
    [
        'customer_id' => $customer_id,
        'org_id' => $activeOrganizationId,
        'start' => (int)$start,
        'limit' => (int)$limit
    ]
);


?>
<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3">
            <div class="d-flex">
                <div class="breadcrumb py-2">
                    <a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
                    <a href="index.php" class="breadcrumb-item">Home</a>
                    <a href="listing_customers.php" class="breadcrumb-item">Customers</a>
                    <span class="breadcrumb-item active">Payments</span>
                </div>
            </div>
        </div>
    </div>
    <!-- /page header -->


    <div class="content-inner">
        <div class="content datatable-enhanced">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">

                <?php include(__DIR__ . '/admin_elements/customer_navbar.php'); ?>

                <div class="col-lg-10">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?php echo $total_pages; ?> Payments Found.</h5>
                        </div>


                        <div class="card-body">

                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo e($error_message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo e($success_message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <div class="table datatable-professional-responsive">
                                <table class="table datatable-professional">

                                    <thead>
                                        <tr>
                                            <th width="150">DATE</th>
                                            <th>PAYMENT #</th>
                                            <th>REFERENCE #</th>
                                            <th>STATUS</th>
                                            <th class="text-end p3">AMOUNT</th>
                                        </tr>
                                    </thead>


                                    <tbody>

                                        <?php

                                        // Calculate serial number start based on page number and limit
                                        $serial_no = ($page_no - 1) * $limit + 1;

                                        foreach ($result_customer_payments as $row) {

                                            $id                     = $row["id"];

                                            $payment_no             = s__($row['payment_no']);
                                            $payment_status         = s__($row['payment_status']);
                                            $total_amount_received  = s__($row['total_amount_received']);
                                            $payment_date           = s__($row['payment_date']);
                                            $reference_no           = s__($row['reference_no']);
                                            $created_at             = s__($row['created_at']);

                                            if (empty($payment_no)) $payment_no = $id;
                                            $payment_date   = ddm_($payment_date);
                                            $created_at     = dd__($created_at);

                                            $status_class = 'secondary';
                                            if ($payment_status === 'paid') $status_class = 'success';
                                            elseif ($payment_status === 'void') $status_class = 'danger';
                                            elseif ($payment_status === 'refund') $status_class = 'warning';
                                        ?>

                                            <tr>
                                                <td><a href="payment_received_overview.php?payment_received_id=<?php echo $id; ?>"><?php echo $payment_date; ?></a></td>
                                                <td><a href="payment_received_overview.php?payment_received_id=<?php echo $id; ?>"><?php echo $payment_no; ?></a></td>
                                                <td><a href="payment_received_overview.php?payment_received_id=<?php echo $id; ?>"><?php echo $reference_no; ?></a></td>
                                                <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo ucwords($payment_status); ?></span></td>
                                                <td class="text-end p3"><a href="payment_received_overview.php?payment_received_id=<?php echo $id; ?>"><?php echo BASE_CURRENCY['code']; ?><?php echo $total_amount_received; ?></a></td>
                                            </tr>

                                        <?php
                                        }
                                        ?>


                                    </tbody>
                                </table>
                            </div>

                        </div>


                    </div>
                </div>



                <!--Pagination -->
                <?php
                if ($page_no == 0) {
                    $page_no = 1;
                }

                $prev = $page_no - 1;
                $next = $page_no + 1;

                $lastpage     = ceil($total_pages / $limit);
                $LastPagem1 = $lastpage - 1;

                $pagination = '';

                if ($lastpage > 1) {
                    $pagination .= '<div class="center-block text-center">';
                    $pagination .= '<ul class="pagination mb-5 mb-lg-0">';

                    if ($page_no > 1) {
                        $pagination    .= '<li class="page-item page-prev"><a class="page-link" href="' . $targetpage . '&page_no=' . $prev . '" tabindex="-1">Prev</a></li>';
                    } else {
                        $pagination    .= '<li class="page-item page-prev disabled"><a class="page-link" href="#" tabindex="-1">Prev</a></li>';
                    }

                    if ($lastpage < 7 + ($stages * 2)) {
                        for ($counter = 1; $counter <= $lastpage; $counter++) {
                            if ($counter == $page_no) {
                                $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                            } else {
                                $pagination    .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $counter . "'>" . $counter . "</a></li>";
                            }
                        }
                    } else if ($lastpage > 5 + ($stages * 2)) {
                        if ($page_no < 1 + ($stages * 2)) {
                            for ($counter = 1; $counter < 4 + ($stages * 2); $counter++) {
                                if ($counter == $page_no) {
                                    $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                                } else {
                                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "'>" . $counter . "</a></li>";
                                }
                            }
                            $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $LastPagem1 . "'>$LastPagem1</a></li>";
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $lastpage . "'>$lastpage</a></li>";
                        } elseif ($lastpage - ($stages * 2) > $page_no && $page_no > ($stages * 2)) {
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=1'>1</a></li>";
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=2'>2</a></li>";
                            $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';
                            for ($counter = $page_no - $stages; $counter <= $page_no + $stages; $counter++) {
                                if ($counter == $page_no) {
                                    $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                                } else {
                                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $counter . "'>" . $counter . "</a></li>";
                                }
                            }
                            $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $LastPagem1 . "'>$LastPagem1</a></li>";
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $lastpage . "'>$lastpage</a></li>";
                        } else {
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='." . $targetpage . "&page_no=1'>1</a></li>";
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=2'>2</a></li>";
                            $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';
                            for ($counter = $lastpage - (2 + ($stages * 2)); $counter <= $lastpage; $counter++) {
                                if ($counter == $page_no) {
                                    $pagination .= '<li class="page-item active"><a class="page-link" href="#">1' . $counter . '</a></li>';
                                } else {
                                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $counter . "'>" . $counter . "</a></li>";
                                }
                            }
                        }
                    }
                    if ($page_no < $counter - 1) {
                        $pagination .= '<li class="page-item page-next"><a class="page-link" href="' . $targetpage . '&page_no=' . $next . '">Next</a></li>';
                    } else {
                        $pagination .= '<li class="page-item page-next"><a class="page-link" href="#">Next</a></li>';
                    }

                    $pagination .= "</ul>";
                    $pagination .= "</div>";
                }

                echo $pagination;
                ?>
                <!--/Pagination -->


            </div>
        </div>


    </div>

    <?php include('admin_elements/copyright.php'); ?>

</div>

<?php include('admin_elements/admin_footer.php'); ?>
