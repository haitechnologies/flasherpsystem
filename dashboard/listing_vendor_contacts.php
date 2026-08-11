<?php


use App\Core\DB;
use App\Core\Session;
use App\Security\Roles;
use App\Security\InputValidator;
include('admin_elements/admin_header.php');

$module             = 'vendor_contacts';
$module_caption     = 'Contact';
$tbl_name             = $tbl_prefix . $module;
$error_message         = '';
$success_message     = '';


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
        log_error('CSRF token validation failed in listing_vendor_contacts.php', 'WARNING', __FILE__, __LINE__);
        $_POST['action'] = '';
    }
}


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

if (isset($_POST['is_active']))       $is_active = 1;
else $is_active = 0;


$vendor_id = '';
if (isset($_REQUEST['vendor_id']))        $vendor_id     = e_s__($_REQUEST['vendor_id']);
if (isset($_POST['vendor_id']))           $vendor_id     = e_s__($_POST['vendor_id']);



$contact_id = 0;
if (isset($_REQUEST['contact_id']))        $contact_id     = e_s__($_REQUEST['contact_id']);
if (isset($_POST['contact_id']))           $contact_id     = e_s__($_POST['contact_id']);



$limit              = 25;
$stages             = 2;


if (isset($_GET['page_no']) && !empty($_GET['page_no'])) {
    $page_no            = e_s__($_GET['page_no']);
} else {
    $page_no            = 1;
}

$targetpage = 'listing_vendor_contacts.php?vendor_id=' . $vendor_id;



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
if (($action == "delete_$module" && !empty($vendor_id)) && granted('delete', $module_id)) {

    // INPUT VALIDATION: Validate contact ID
    $idResult = InputValidator::integer($contact_id, 1);
    if (!$idResult['valid']) {
        $error_message = "Invalid contact ID: " . $idResult['error'];
    } else {
        $validContactId = $idResult['value'];

        //SUPERADMIN CAN DELETE ANY DATA
        if (Roles::hasFullAccess($session_role_id)) {

            // Perform delete with prepared statement
            $stmt = $mysqli->prepare("DELETE FROM `" . DB::VENDOR_CONTACTS . "` WHERE id=? AND contactable_type='Vendor'");
            $stmt->bind_param("i", $validContactId);

            if ($stmt->execute()) {
                // Vendor Logs
                updateVendorLogs($vendor_id, 'contacts', 'deleted');
                $success_message = "$module_caption Deleted Successfully.";
            } else {
                $error_message = "Database error: " . $stmt->error;
                log_error("Delete failed for contact $validContactId: " . $stmt->error, 'ERROR', __FILE__, __LINE__);
            }
            $stmt->close();

            //ADMIN CAN DELETE ONLY HIS/HER DATA
        } else {
            // Verify ownership before deleting
            $ownershipCheck = $mysqli->prepare("SELECT id, created_by FROM `" . DB::VENDOR_CONTACTS . "` WHERE id=? AND contactable_type='Vendor' AND created_by=?");
            $ownershipCheck->bind_param("is", $validContactId, Session::userId());
            $ownershipCheck->execute();
            $ownershipResult = $ownershipCheck->get_result();
            $ownershipCheck->close();

            if ($ownershipResult->num_rows === 0) {
                $error_message = "You do not have permission to delete this contact";
                log_error("IDOR attempt: User " . Session::userId() . " tried to delete contact $validContactId", 'WARNING', __FILE__, __LINE__);
            } else {
                // Perform delete with prepared statement
                $stmt = $mysqli->prepare("DELETE FROM `" . DB::VENDOR_CONTACTS . "` WHERE id=? AND contactable_type='Vendor' AND created_by=?");
                $stmt->bind_param("is", $validContactId, Session::userId());

                if ($stmt->execute()) {
                    // Vendor Logs
                    updateVendorLogs($vendor_id, 'contacts', 'deleted');
                    $success_message = "$module_caption Deleted Successfully.";
                } else {
                    $error_message = "Database error: " . $stmt->error;
                    log_error("Delete failed for contact $validContactId: " . $stmt->error, 'ERROR', __FILE__, __LINE__);
                }
                $stmt->close();
            }
        }
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
$result         = $mysqli->query("SELECT id FROM `" . DB::VENDOR_CONTACTS . "` WHERE id>0 AND contactable_type = 'Vendor' AND contactable_id=$vendor_id ");
$total_pages      = $result->num_rows;

//NORMAL QUERY
$result_vendor_contacts = $mysqli->query("SELECT * FROM `" . DB::VENDOR_CONTACTS . "` WHERE id>0 AND contactable_type = 'Vendor' AND contactable_id=$vendor_id ORDER BY id DESC LIMIT $start, $limit");



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
                        <a href="listing_vendors.php" class="breadcrumb-item">Vendors</a>
                        <span class="breadcrumb-item active">Contacts</span>
                    </div>

                    <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                    </a>
                </div>

                <div class="collapse d-lg-block ms-lg-auto mt-1" id="breadcrumb_elements">
                    <div class="d-lg-flex mb-2 mb-lg-0">

                        <?php if (isset($module_id) && granted('create', $module_id)) { ?>
                            <button type="button" class="btn btn-info my-1 me-2 nav-link" data-href="<?php echo $admin_base_url; ?>/vendor_contacts.php?vendor_id=<?php echo $vendor_id; ?>">Create Contact</button>
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

                    <?php include(__DIR__ . '/admin_elements/vendor_navbar.php'); ?>

                    <div class="col-lg-6 col-xl-10">

                        <div class="row">
                            <div class="col-lg-12">


                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><?php echo $total_pages; ?> Contacts Found.</h5>
                                    </div>


                                    <div class="card-body">

                                        <div class="table datatable-professional-responsive">
                                            <table class="table datatable-professional">

                                                <thead>
                                                    <tr>
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

                                                    $sr = 1;
                                                    while ($row_vendor_contacts = $result_vendor_contacts->fetch_array(MYSQLI_ASSOC)) {

                                                        $contact_id             = $row_vendor_contacts["id"];

                                                        $vendor_id              = s__($row_vendor_contacts['contactable_id']);
                                                        $first_name             = s__($row_vendor_contacts['first_name']);
                                                        $last_name              = s__($row_vendor_contacts['last_name']);
                                                        $position               = s__($row_vendor_contacts['position']);
                                                        $email                  = s__($row_vendor_contacts['email']);
                                                        $phone                  = s__($row_vendor_contacts['phone']);
                                                        $notes                  = s__($row_vendor_contacts['notes']);

                                                        $created_at             = s__($row_vendor_contacts['created_at']);
                                                        $is_active = s__($row_vendor_contacts['is_active']);

                                                        if ($is_active == 0)
                                                            $status = '<span class="badge bg-warning">InActive</span>';
                                                        else
                                                            $status = '<span class="badge bg-success">Active</span>';


                                                        $created_at     = date('Y-m-d h:i:s', strtotime($created_at));
                                                    ?>

                                                        <tr>
                                                            <td><a href="vendor_contacts.php?action=edit_vendor_contacts&contact_id=<?php echo $contact_id; ?>&vendor_id=<?php echo $vendor_id; ?>"><?php echo $first_name; ?></a></td>
                                                            <td><a href="vendor_contacts.php?action=edit_vendor_contacts&contact_id=<?php echo $contact_id; ?>&vendor_id=<?php echo $vendor_id; ?>"><?php echo $last_name; ?></a></td>
                                                            <td><a href="vendor_contacts.php?action=edit_vendor_contacts&contact_id=<?php echo $contact_id; ?>&vendor_id=<?php echo $vendor_id; ?>"><?php echo $position; ?></a></td>
                                                            <td><a href="vendor_contacts.php?action=edit_vendor_contacts&contact_id=<?php echo $contact_id; ?>&vendor_id=<?php echo $vendor_id; ?>"><?php echo $email; ?></a></td>
                                                            <td><a href="vendor_contacts.php?action=edit_vendor_contacts&contact_id=<?php echo $contact_id; ?>&vendor_id=<?php echo $vendor_id; ?>"><?php echo $phone; ?></a></td>
                                                            <td><a href="vendor_contacts.php?action=edit_vendor_contacts&contact_id=<?php echo $contact_id; ?>&vendor_id=<?php echo $vendor_id; ?>"><?php echo $notes; ?></a></td>
                                                            <td><?php echo $created_at; ?></td>
                                                            <td><?php echo $status; ?></td>
                                                            <td>

                                                                <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                                                                    <a href="vendor_contacts.php?action=edit_vendor_contacts&contact_id=<?php echo $contact_id; ?>&vendor_id=<?php echo $vendor_id; ?>"><i class="ph-pencil"></i></a>
                                                                <?php } ?>

                                                                <?php if (isset($module_id) && granted('delete', $module_id)) { ?>
                                                                    <a href="listing_vendor_contacts.php?action=delete_vendor_contacts&contact_id=<?php echo $contact_id; ?>&vendor_id=<?php echo $vendor_id; ?>"><i class="ph-trash"></i></a>
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

                        </div>




                        <div class="row">

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

                                // PREVIOUS
                                if ($page_no > 1) {
                                    $pagination    .= '<li class="page-item page-prev"><a class="page-link" href="' . $targetpage . '&page_no=' . $prev . '" tabindex="-1">Prev</a></li>';
                                } else {
                                    $pagination    .= '<li class="page-item page-prev disabled"><a class="page-link" href="#" tabindex="-1">Prev</a></li>';
                                }

                                // Pages
                                if ($lastpage < 7 + ($stages * 2)) {
                                    for ($counter = 1; $counter <= $lastpage; $counter++) {
                                        if ($counter == $page_no) {
                                            $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                                        } else {
                                            $pagination    .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $counter . "'>" . $counter . "</a></li>";
                                        }
                                    }
                                } else if ($lastpage > 5 + ($stages * 2)) {
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
