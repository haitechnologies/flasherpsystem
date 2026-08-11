<?php


use App\Core\DB;
use App\Core\Session;
use App\Security\Roles;
use App\Security\InputValidator;
include('admin_elements/admin_header.php');
// Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/InputValidator.php';

$module             = 'customer_contacts';
$module_caption     = 'Contact';
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


/*
|--------------------------------------------------------------------------
| 	PAGINATION
|--------------------------------------------------------------------------
|
*/

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

if (isset($_POST['is_active']))       $is_active = 1;
else $is_active = 0;


$customer_id = '';
if (isset($_REQUEST['customer_id']))        $customer_id     = e_s__($_REQUEST['customer_id']);
if (isset($_POST['customer_id']))           $customer_id     = e_s__($_POST['customer_id']);



$contact_id = 0;
if (isset($_REQUEST['contact_id']))        $contact_id     = e_s__($_REQUEST['contact_id']);
if (isset($_POST['contact_id']))           $contact_id     = e_s__($_POST['contact_id']);





$limit              = 10;
$stages             = 2;


if (isset($_GET['page_no']) && !empty($_GET['page_no'])) {
    $page_no            = e_s__($_GET['page_no']);
} else {
    $page_no            = 1;
}

$targetpage = 'listing_customer_contacts.php?customer_id=' . $id;

// $targetpage            = 'report_bookings.php?action=generate_report&date_from=' . $date_from . '&date_to=' . $date_to . '&ticket_enumber=' . $ticket_enumber . '&booking_full_name=' . $booking_full_name . '&booking_mobile=' . $booking_mobile . '&is_free=' . $is_free . '&ticket_type=' . $ticket_type . '&check=' . $check . '&pax=' . $pax . '&batch_id=' . $batch_id . '&total_cost=' . $total_cost . '&grand_total=' . $grand_total . '&booking_status=' . $booking_status;



if ($page_no) {
    $start   = ($page_no - 1) * $limit;
} else {
    $start   = 0;
}



/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


// print_r($_REQUEST);

if (isset($_REQUEST['customer_id']) && !empty($_REQUEST['customer_id'])) {
    $customer_id     = e_s__($_REQUEST['customer_id']);
} else {
    $customer_id = 0;
}



/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
|
*/
if (($action == "delete_$module" && !empty($customer_id)) && granted('delete', $module_id)) {

    // INPUT VALIDATION: Validate contact ID
    $idResult = InputValidator::integer($contact_id, 1);
    if (!$idResult['valid']) {
        $error_message = "Invalid contact ID: " . $idResult['error'];
    } else {
        $validContactId = $idResult['value'];
        
        //SUPERADMIN CAN DELETE ANY DATA
        if (Roles::hasFullAccess($session_role_id)) {

            // Perform delete with prepared statement
            $stmt = $mysqli->prepare("DELETE FROM `" . DB::CUSTOMER_CONTACTS . "` WHERE id=? AND contactable_type='Customer'");
            $stmt->bind_param("i", $validContactId);
            
            if ($stmt->execute()) {
                // Customer Logs
                updateCustomerLogs($customer_id, 'contacts', 'deleted');
                $success_message = "Contact deleted successfully.";
            } else {
                $error_message = "Database error: " . $stmt->error;
                log_error("Delete failed for contact $validContactId: " . $stmt->error, 'ERROR', __FILE__, __LINE__);
            }
            $stmt->close();

            //ADMIN CAN DELETE ONLY HIS/HER DATA
        } else {
            // Verify ownership before deleting
            $ownershipCheck = $mysqli->prepare("SELECT id, created_by FROM `" . DB::CUSTOMER_CONTACTS . "` WHERE id=? AND contactable_type='Customer' AND created_by=?");
            $ownershipCheck->bind_param("is", $validContactId, Session::userId());
            $ownershipCheck->execute();
            $ownershipResult = $ownershipCheck->get_result();
            $ownershipCheck->close();
            
            if ($ownershipResult->num_rows === 0) {
                $error_message = "You do not have permission to delete this contact";
                log_error("IDOR attempt: User " . Session::userId() . " tried to delete contact $validContactId", 'WARNING', __FILE__, __LINE__);
            } else {
                // Perform delete with prepared statement
                $stmt = $mysqli->prepare("DELETE FROM `" . DB::CUSTOMER_CONTACTS . "` WHERE id=? AND contactable_type='Customer' AND created_by=?");
                $stmt->bind_param("is", $validContactId, Session::userId());
                
                if ($stmt->execute()) {
                    // Customer Logs
                    updateCustomerLogs($customer_id, 'contacts', 'deleted');
                    $success_message = "Contact deleted successfully.";
                } else {
                    $error_message = "Database error: " . $stmt->error;
                    log_error("Delete failed for contact $validContactId: " . $stmt->error, 'ERROR', __FILE__, __LINE__);
                }
                $stmt->close();
            }
        }
    }


    if ($result) {
        $success_message = "$module_caption Deleted Successfully.";
        // header("Location:listing_$module.php?page=$page&success_message=$success_message");
    } else {
        $error_message = "Sorry! $module Could Not Be Deleted.";
    }
}



if (isset($_POST['is_active']))                                 $is_active = 1;
else $is_active = 0;


/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/

//COUNT QUERY
$result         = $mysqli->query("SELECT id FROM `" . DB::CUSTOMER_CONTACTS . "` WHERE id>0 AND contactable_type = 'Customer' AND contactable_id=$customer_id ");
$total_pages      = $result->num_rows;

//NORMAL QUERY
$result_customer_contacts = $mysqli->query("SELECT * FROM `" . DB::CUSTOMER_CONTACTS . "` WHERE id>0 AND contactable_type = 'Customer' AND contactable_id=$customer_id ORDER BY id DESC LIMIT $start, $limit");



/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>
<div class="content-wrapper">


    <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
        <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>
            <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
            <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
        <?php } else { ?>
            <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
        <?php } ?>

        <!-- Page header -->
        <div class="page-header page-header-light shadow carriers-page-header">
            <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 carriers-page-header-content py-2 px-3">
                <div class="d-flex">
                    <div class="breadcrumb py-2">
                        <a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
                        <a href="index.php" class="breadcrumb-item">Home</a>
                        <a href="listing_customers.php" class="breadcrumb-item">Customers</a>
                        <span class="breadcrumb-item active">Proposals</span>
                    </div>

                    <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                    </a>
                </div>

                <div class="collapse d-lg-block ms-lg-auto mt-1" id="breadcrumb_elements">
                    <div class="d-lg-flex mb-2 mb-lg-0">

                        <?php if (isset($module_id) && granted('create', $module_id)) { ?>
                            <button type="button" class="btn btn-info my-1 me-2 nav-link" data-href="<?php echo $admin_base_url; ?>/customer_contacts.php?customer_id=<?php echo $customer_id; ?>">Create Contact</button>
                        <?php } ?>

                    </div>
                </div>

            </div>
        </div>
        <!-- /page header -->


        <div class="content-inner">
            <div class="content datatable-enhanced">

                <?php include('admin_elements/breadcrumb.php'); ?>

                <div class="row">

                    <!-- <div class="col-lg-6 col-xl-2"> -->
                    <?php include(__DIR__ . '/admin_elements/customer_navbar.php'); ?>
                    <!-- </div> -->

                    <div class="col-lg-6 col-xl-10">

                        <div class="row">
                            <div class="col-lg-12">


                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><?php echo $total_pages; ?> Contacts Found.</h5>
                                    </div>


                                    <div class="card-body">

                                        <div class="table datatable-professional-responsive">
                                            <table class="table datatable-professional">

                                                <thead>
                                                    <tr>
                                                        <th width="80">SR.</th>
                                                        <th>FIRST NAME</th>
                                                        <th>LAST NAME</th>
                                                        <th>POSITION</th>
                                                        <th>EMAIL</th>
                                                        <th>PHONE</th>
                                                        <th>NOTES</th>
                                                        <th>DATE CREATED</th>
                                                        <th>STATUS</th>
                                                        <th width="120">ACTION</th>
                                                    </tr>
                                                </thead>


                                                <tbody>

                                                    <?php

                                                    // ---------------------------------------------------------------------------------------
                                                    while ($row_customer_contacts = $result_customer_contacts->fetch_array(MYSQLI_ASSOC)) {

                                                        $contact_id             = $row_customer_contacts["id"];

                                                        $customer_id            = s__($row_customer_contacts['contactable_id']);
                                                        $first_name             = s__($row_customer_contacts['first_name']);
                                                        $last_name              = s__($row_customer_contacts['last_name']);
                                                        $position               = s__($row_customer_contacts['position']);
                                                        $email                  = s__($row_customer_contacts['email']);
                                                        $phone                  = s__($row_customer_contacts['phone']);
                                                        $notes                  = s__($row_customer_contacts['notes']);

                                                        $created_at             = s__($row_customer_contacts['created_at']);
                                                        $is_active = s__($row_customer_contacts['is_active']);

                                                        if ($is_active == 0)
                                                            $status = '<span class="badge bg-warning">InActive</span>';
                                                        else
                                                            $status = '<span class="badge bg-success">Active</span>';


                                                        $created_at     = ddm_($created_at);

                                                        // ---------------------------------------------------------------------------------------
                                                    ?>

                                                        <tr>
                                                            <td><a href="customer_contacts.php?action=edit_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"><?php echo $contact_id; ?></a></td>
                                                            <td><a href="customer_contacts.php?action=edit_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"><?php echo $first_name; ?></a></td>
                                                            <td><a href="customer_contacts.php?action=edit_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"><?php echo $last_name; ?></a></td>
                                                            <td><a href="customer_contacts.php?action=edit_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"><?php echo $position; ?></a></td>
                                                            <td><a href="customer_contacts.php?action=edit_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"><?php echo $email; ?></a></td>
                                                            <td><a href="customer_contacts.php?action=edit_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"><?php echo $phone; ?></a></td>
                                                            <td><a href="customer_contacts.php?action=edit_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"><?php echo $notes; ?></a></td>
                                                            <td><a href="customer_contacts.php?action=edit_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"></a><?php echo $created_at; ?></td>
                                                            <td><a href="customer_contacts.php?action=edit_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"></a><?php echo $status; ?></td>
                                                            <td>

                                                                <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                                                                    <a href="customer_contacts.php?action=edit_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"><i class="ph-pencil"></i></a>
                                                                <?php } ?>

                                                                <?php if (isset($module_id) && granted('delete', $module_id)) { ?>
                                                                    <a href="listing_customer_contacts.php?action=delete_customer_contacts&contact_id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>"><i class="ph-trash"></i></a>
                                                                <?php } ?>

                                                            </td>
                                                        </tr>

                                                    <?php
                                                    } //while
                                                    ?>


                                                </tbody>
                                            </table>
                                        </div>

                                    </div>


                                </div>


                            </div>

                            <!-- <div class="col-lg-4">
                                <div class="card">
                                </div>
                            </div> -->

                        </div>




                        <div class="row">

                            <!--Pagination -->
                            <?php
                            // @@@ PAGINATION ALGO @@@ //

                            if ($page_no == 0) {
                                $page_no = 1;
                            }

                            // echo $page_no;

                            $prev = $page_no - 1;
                            $next = $page_no + 1;

                            $lastpage     = ceil($total_pages / $limit);
                            $LastPagem1 = $lastpage - 1;

                            $pagination = '';

                            if ($lastpage > 1) {
                                $pagination .= '<div class="center-block text-center">';
                                $pagination .= '<ul class="pagination mb-5 mb-lg-0">';

                                // PREVIOUS
                                if ($page_no > 1) {
                                    $pagination    .= '<li class="page-item page-prev"><a class="page-link" href="' . $targetpage . '&page_no=' . $prev . '" tabindex="-1">Prev</a></li>';
                                } else {
                                    $pagination    .= '<li class="page-item page-prev disabled"><a class="page-link" href="#" tabindex="-1">Prev</a></li>';
                                }

                                // Pages
                                if ($lastpage < 7 + ($stages * 2))    // Not enough pages to breaking it up
                                {
                                    for ($counter = 1; $counter <= $lastpage; $counter++) {
                                        if ($counter == $page_no) {
                                            $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                                        } else {
                                            $pagination    .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $counter . "'>" . $counter . "</a></li>";
                                        }
                                    }
                                } else if ($lastpage > 5 + ($stages * 2))    // Enough pages to hide a few?
                                {
                                    // Beginning only hide later pages
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
                                    }
                                    // Middle hide some front and some back
                                    elseif ($lastpage - ($stages * 2) > $page_no && $page_no > ($stages * 2)) {
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
                                    }
                                    // End only hide early pages
                                    else {
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
                                // Next
                                if ($page_no < $counter - 1) {
                                    $pagination .= '<li class="page-item page-next"><a class="page-link" href="' . $targetpage . '&page_no=' . $next . '">Next</a></li>';
                                } else {
                                    $pagination .= '<li class="page-item page-next"><a class="page-link" href="#">Next</a></li>';
                                }

                                $pagination .= "</ul>";
                                $pagination .= "</div>";
                            } //endif

                            echo $pagination;
                            ?>
                            <!--/Pagination -->

                            <?php
                            // } // endif
                            ?>


                        </div>


                    </div>
                </div>



            </div>


        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
    </form>

</div>

<?php include('admin_elements/admin_footer.php'); ?>


