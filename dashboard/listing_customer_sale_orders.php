<?php

use App\Core\DB;
use App\Security\Roles;
use App\Security\InputValidator;
use App\Core\Container;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module             = 'sale_orders';
$module_caption     = 'Sale Order';
$error_message      = '';
$success_message    = '';
$hide_add_button    = true;

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_customer_sale_orders.php', 'WARNING', __FILE__, __LINE__);
        $_POST['action'] = '';
    }
}

if (isset($_REQUEST['customer_id']) && !empty($_REQUEST['customer_id'])) {
    $customer_id = e_s__($_REQUEST['customer_id']);
} else {
    $customer_id = 0;
}

$limit  = 25;
$stages = 2;

if (isset($_GET['page_no']) && !empty($_GET['page_no'])) {
    $page_no = (int)e_s__($_GET['page_no']);
} else {
    $page_no = 1;
}

$targetpage = 'listing_customer_sale_orders.php?customer_id=' . $customer_id;

if ($page_no) {
    $start = ($page_no - 1) * $limit;
} else {
    $start = 0;
}

$db = Container::getInstance()->get(\App\Core\Database::class);

$action = $_POST['action'] ?? $_REQUEST['action'] ?? '';
$id = $_POST['id'] ?? $_REQUEST['id'] ?? 0;

if (($action == "delete_$module" && !empty($id))) {
    $idResult = InputValidator::integer($id, 1);
    if (!$idResult['valid']) {
        $error_message = "Invalid sale order ID: " . $idResult['error'];
    } else {
        $validId = $idResult['value'];
        try {
            $saleOrderService = Container::getInstance()->get(\App\Service\SaleOrderService::class);

            if (!Roles::hasFullAccess(Session::roleId())) {
                $so = $saleOrderService->getSaleOrder($validId, $activeOrganizationId);
                if ($so->createdBy !== Session::userId()) {
                    throw new \Exception("You do not have permission to delete this sale order");
                }
            }

            if ($saleOrderService->deleteSaleOrder($validId, $activeOrganizationId)) {
                $success_message = "$module_caption Deleted Successfully.";
                flash_success($success_message);
                header("Location:listing_customer_sale_orders.php?customer_id=$customer_id");
                exit;
            } else {
                $error_message = "Sorry! $module_caption Could Not Be Deleted.";
            }
        } catch (\Throwable $e) {
            $error_message = $e->getMessage();
            log_error("Delete failed for sale order $validId: " . $e->getMessage(), 'ERROR', __FILE__, __LINE__);
        }
    }
}

$countRow = $db->fetchOne(
    "SELECT COUNT(id) as cnt FROM `" . DB::SALE_ORDERS . "` WHERE customer_id = :customer_id AND organization_id = :org_id",
    ['customer_id' => $customer_id, 'org_id' => $activeOrganizationId]
);
$total_pages = (int)($countRow['cnt'] ?? 0);

$result = $db->fetchAll(
    "SELECT * FROM `" . DB::SALE_ORDERS . "` WHERE customer_id = :customer_id AND organization_id = :org_id ORDER BY id DESC LIMIT :start, :limit",
    [
        'customer_id' => $customer_id,
        'org_id'     => $activeOrganizationId,
        'start'      => (int)$start,
        'limit'      => (int)$limit
    ]
);

?>
<div class="content-wrapper">
    <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
        <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>
            <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
            <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
        <?php } else { ?>
            <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
        <?php } ?>

        <div class="page-header page-header-light shadow carriers-page-header">
            <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3">
                <div class="d-flex">
                    <div class="breadcrumb py-2">
                        <a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
                        <a href="index.php" class="breadcrumb-item">Home</a>
                        <a href="listing_customers.php" class="breadcrumb-item">Customers</a>
                        <span class="breadcrumb-item active">Sale Orders</span>
                    </div>
                    <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                    </a>
                </div>
                <?php if (isset($module_id) && granted('create', $module_id)) { ?>
                    <div class="collapse d-lg-block ms-lg-auto mt-1" id="breadcrumb_elements">
                        <div class="d-lg-flex mb-2 mb-lg-0">
                            <a href="sale_orders.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-info my-1 me-2 nav-link">Create Sale Order</a>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="content-inner">
            <div class="content datatable-enhanced">
                <?php include('admin_elements/breadcrumb.php'); ?>
                <div class="row">
                    <?php include(__DIR__ . '/admin_elements/customer_navbar.php'); ?>
                    <div class="col-lg-10">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo $total_pages; ?> Sale Orders Found.</h5>
                            </div>
                            <div class="card-body">
                                <div class="table datatable-professional-responsive">
                                    <table class="table datatable-professional">
                                        <thead>
                                            <tr>
                                                <th width="150">DATE</th>
                                                <th>SALE ORDER#</th>
                                                <th>REFERENCE#</th>
                                                <th>CUSTOMER NAME</th>
                                                <th>STATUS</th>
                                                <th class="text-end p3">AMOUNT</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $serial_no = ($page_no - 1) * $limit + 1;
                                            foreach ($result as $row) {
                                                $id              = $row['id'];
                                                $cuId            = s__($row['customer_id']);
                                                $display_name    = getTableAttr('display_name', DB::CUSTOMERS, $cuId);
                                                $sale_order_no   = s__($row['sale_order_no']);
                                                $sale_order_date = ddm_(s__($row['sale_order_date']));
                                                $sale_order_date = ($sale_order_date === '1970-01-01') ? '' : $sale_order_date;
                                                $reference_no    = s__($row['reference_no']);
                                                $so_status       = s__($row['sale_order_status']);
                                                $grand_total     = s__($row['grand_total']);
                                            ?>
                                                <tr>
                                                    <td><a href="sale_order_overview.php?sale_order_id=<?php echo $id; ?>"><?php echo $sale_order_date; ?></a></td>
                                                    <td><a href="sale_order_overview.php?sale_order_id=<?php echo $id; ?>"><?php echo $sale_order_no; ?></a></td>
                                                    <td><a href="sale_order_overview.php?sale_order_id=<?php echo $id; ?>"><?php echo $reference_no; ?></a></td>
                                                    <td><a href="sale_order_overview.php?sale_order_id=<?php echo $id; ?>"><?php echo $display_name; ?></a></td>
                                                    <td><a href="sale_order_overview.php?sale_order_id=<?php echo $id; ?>" target="_blank"><span class="badge text-dark"><?php echo ucwords($so_status); ?></span></a></td>
                                                    <td class="text-end p3"><a href="sale_order_overview.php?sale_order_id=<?php echo $id; ?>"><?php echo BASE_CURRENCY['code']; ?><?php echo $grand_total; ?></a></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                    if ($page_no == 0) { $page_no = 1; }
                    $prev       = $page_no - 1;
                    $next       = $page_no + 1;
                    $lastpage   = (int)ceil($total_pages / $limit);
                    $LastPagem1 = $lastpage - 1;
                    $pagination = '';

                    if ($lastpage > 1) {
                        $pagination .= '<div class="center-block text-center">';
                        $pagination .= '<ul class="pagination mb-5 mb-lg-0">';
                        if ($page_no > 1) {
                            $pagination .= '<li class="page-item page-prev"><a class="page-link" href="' . $targetpage . '&page_no=' . $prev . '" tabindex="-1">Prev</a></li>';
                        } else {
                            $pagination .= '<li class="page-item page-prev disabled"><a class="page-link" href="#" tabindex="-1">Prev</a></li>';
                        }
                        if ($lastpage < 7 + ($stages * 2)) {
                            for ($counter = 1; $counter <= $lastpage; $counter++) {
                                if ($counter == $page_no) {
                                    $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                                } else {
                                    $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=' . $counter . '">' . $counter . '</a></li>';
                                }
                            }
                        } else if ($lastpage > 5 + ($stages * 2)) {
                            if ($page_no < 1 + ($stages * 2)) {
                                for ($counter = 1; $counter < 4 + ($stages * 2); $counter++) {
                                    if ($counter == $page_no) {
                                        $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                                    } else {
                                        $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=' . $counter . '">' . $counter . '</a></li>';
                                    }
                                }
                                $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';
                                $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=' . $LastPagem1 . '">' . $LastPagem1 . '</a></li>';
                                $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=' . $lastpage . '">' . $lastpage . '</a></li>';
                            } elseif ($lastpage - ($stages * 2) > $page_no && $page_no > ($stages * 2)) {
                                $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=1">1</a></li>';
                                $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=2">2</a></li>';
                                $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';
                                for ($counter = $page_no - $stages; $counter <= $page_no + $stages; $counter++) {
                                    if ($counter == $page_no) {
                                        $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                                    } else {
                                        $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=' . $counter . '">' . $counter . '</a></li>';
                                    }
                                }
                                $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';
                                $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=' . $LastPagem1 . '">' . $LastPagem1 . '</a></li>';
                                $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=' . $lastpage . '">' . $lastpage . '</a></li>';
                            } else {
                                $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=1">1</a></li>';
                                $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=2">2</a></li>';
                                $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';
                                for ($counter = $lastpage - (2 + ($stages * 2)); $counter <= $lastpage; $counter++) {
                                    if ($counter == $page_no) {
                                        $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                                    } else {
                                        $pagination .= '<li class="page-item"><a class="page-link" href="' . $targetpage . '&page_no=' . $counter . '">' . $counter . '</a></li>';
                                    }
                                }
                            }
                        }
                        if ($page_no < $counter - 1) {
                            $pagination .= '<li class="page-item page-next"><a class="page-link" href="' . $targetpage . '&page_no=' . $next . '">Next</a></li>';
                        } else {
                            $pagination .= '<li class="page-item page-next"><a class="page-link" href="#">Next</a></li>';
                        }
                        $pagination .= "</ul></div>";
                    }
                    echo $pagination;
                    ?>
                </div>
            </div>
            <?php include('admin_elements/copyright.php'); ?>
        </div>
    </form>
</div>
<?php include('admin_elements/admin_footer.php'); ?>
