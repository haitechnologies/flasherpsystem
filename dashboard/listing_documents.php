<?php


use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

if (!defined('tbl_trips'))           { define('tbl_trips',           DB::getPrefix() . 'trips'); }
if (!defined('tbl_vehicles'))       { define('tbl_vehicles',       DB::getPrefix() . 'vehicles'); }
if (!defined('tbl_vehicle_types'))  { define('tbl_vehicle_types',  DB::getPrefix() . 'vehicle_types'); }

$module = 'documents';
$module_caption = 'Documents';
$tbl_name = DB::USERS;
$error_message = '';
$success_message = '';
$page = (int)($_GET['page'] ?? 1);

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();


$limit                 = 18;
$stages             = 2;

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

$driver_id = 0;

if (isset($_REQUEST['driver_id']) && !empty($_REQUEST['driver_id']))
    $driver_id     = e_s__($_REQUEST['driver_id']);


$vehicle_id = 0;

if (isset($_REQUEST['vehicle_id']) && !empty($_REQUEST['vehicle_id']))
    $vehicle_id     = e_s__($_REQUEST['vehicle_id']);



/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/

if (isset($_GET['page_no']) && !empty($_GET['page_no'])) {
    $page_no            = e_s__($_GET['page_no']);
} else {
    $page_no            = 1;
}

if (isset($_GET['is_active']) && !empty($_GET['is_active'])) {
    $is_active            = e_s__($_GET['is_active']);
} else {
    $is_active            = 'all';
}


$targetpage            = 'listing_documents.php?is_active=' . $is_active;



/*
|--------------------------------------------------------------------------
| ASSIGN VEHICLE TO DRIVER
|--------------------------------------------------------------------------
|
*/
if ($action == "assign_vehicle" && !empty($vehicle_id) && !empty($driver_id)) {


    // echo "UPDATE `" . DB::USERS . "` SET vehicle_id = $vehicle_id WHERE id= $driver_id";
    $result = $mysqli->query("UPDATE `" . DB::USERS . "` SET vehicle_id = $vehicle_id WHERE id= $driver_id");


    if ($result) {
        $success_message = "Vehicle is Assigned to the Driver Successfully.";
        // header("Location:listing_$module.php?page=$page&success_message=$success_message");
    } else {
        $error_message = "Sorry! Vehicle Could Not Be Assigned to the Driver.";
    }

    /*
|--------------------------------------------------------------------------
| UN-ASSIGN VEHICLE TO DRIVER
|--------------------------------------------------------------------------
|
*/
} else if ($action == "unassign_vehicle" && !empty($driver_id)) {


    $result = $mysqli->query("UPDATE `" . DB::USERS . "` SET vehicle_id = 0 WHERE id=$driver_id");


    if ($result) {
        $success_message = "Vehicle is Un-Assigned to the Driver Successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php?page=$page");
    } else {
        $error_message = "Sorry! Vehicle Could Not Be Un-Assigned to the Driver.";
    }


    /*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
|
*/
} else if (($action == "delete_$module" && !empty($id))) {

    //SUPERADMIN CAN DELETE ANY DATA
    if ($_SESSION[$project_pre]['DASHBOARD']['type'] == 'superadmin') {

        $photo = getTableAttr('photo', $tbl_name, $id);
        $result = $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id");

        if (!empty($photo)) {
            delete_photo($photo, $photo_upload_path, '1');     // DELETE OLD THUMB
            delete_photo($photo, $photo_upload_path, '0');        // DELETE OLD PHOTO
        }

        //ADMIN CAN DELETE ONLY HIS/HER DATA
    } else {

        $photo = getTableAttr('photo', $tbl_name, $id);
        $result = $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND created_by='" . $_SESSION[$project_pre]['DASHBOARD']['admin_id'] . "'");

        if (!empty($photo)) {
            delete_photo($photo, $photo_upload_path, '1');     // DELETE OLD THUMB
            delete_photo($photo, $photo_upload_path, '0');        // DELETE OLD PHOTO
        }
    }


    if ($result) {
        $success_message = "$module_caption Deleted Successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php?page=$page");
    } else {
        $error_message = "Sorry! $module Could Not Be Deleted.";
    }
}

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>

<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 carriers-page-header-content py-2 px-3">
            <div class="d-flex">
                <div class="breadcrumb py-2">
                    <a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
                    <a href="index.php" class="breadcrumb-item">Home</a>
                    <span class="breadcrumb-item active">Drivers</span>
                    <span class="breadcrumb-item active">Listing</span>
                </div>

                <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                    <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                </a>
            </div>

            <?php if (granted_('create', 'drivers')) { ?>
                <div class="collapse d-lg-block ms-lg-auto" id="breadcrumb_elements">
                    <div class="d-lg-flex mb-2 mb-lg-0">
                        <button type="button" onclick="window.location.href='drivers.php';" class=" btn btn-success my-1 me-2">Create</button>
                    </div>
                </div>
            <?php } ?>

        </div>
    </div>
    <!-- /page header -->


    <div class="content">

        <?php include('admin_elements/breadcrumb.php'); ?>

        <div class="row">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">

                        <h5 class="mb-0">Documents</h5>

                        <ul class="nav nav-tabs nav-tabs-solid nav-justified rounded col-lg-6">
                            <li class="nav-item">
                                <a href="listing_<?php echo $module; ?>.php?is_active=all" class="nav-link rounded-start <?php echo (($is_active == 'all') ? 'active' : '') ?> ">All</a>
                            </li>
                            <li class="nav-item bg-success">
                                <a href="listing_documents.php?is_active=on_route" class="nav-link text-white <?php echo (($is_active == 'on_route') ? 'active' : '') ?>">UP-TO-DATE</a>
                            </li>
                            <li class="nav-item bg-warning">
                                <a href="listing_documents.php?is_active=booked" class="nav-link text-white <?php echo (($is_active == 'booked') ? 'active' : '') ?>">NEAR EXPIRY</a>
                            </li>
                            <li class="nav-item bg-danger">
                                <a href="listing_documents.php?is_active=free" class="nav-link text-white <?php echo (($is_active == 'free') ? 'active' : '') ?>">EXPIRED</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <!-- Available Quotation Status: &nbsp; -->
                <span class="badge bg-success"> 125 &nbsp; UPDATE-TO-DATE </span>
                <span class="badge bg-warning"> 25 &nbsp; NEAR EXPIRY </span>
                <span class="badge bg-danger"> 10 &nbsp; EXPIRED </span>
                <!-- <span class="badge bg-black"> Rejected</span> -->
                <span class="badge bg-black"> 16 &nbsp; NEW </span>
                <!-- <span class="badge bg-indigo">Booked</span> -->
            </div>
        </div>




        <div class="row">
            <?php

            if ($page_no) {
                $start   = ($page_no - 1) * $limit;
            } else {
                $start   = 0;
            }


            $search_query = '';

            if ($is_active == 'booked') {
                $search_query = " AND id IN (SELECT driver_id FROM `" . tbl_trips . "` WHERE requested_date_time = '" . date('Y-m-d') . "' )";
            } else if ($is_active == 'free') {
                $search_query = " AND id NOT IN (SELECT driver_id FROM `" . tbl_trips . "` WHERE requested_date_time = '" . date('Y-m-d') . "' )";
            } else if ($is_active == 'on_route') {
                $search_query = " AND id IN (SELECT driver_id FROM `" . tbl_trips . "` WHERE trip_status = 'started' )";
            }

            //COUNT QUERY
            // $result = $mysqli->query("SELECT id FROM `" . DB::USERS . "` WHERE id>0 " . $search_query);
            $result = $mysqli->query("SELECT id FROM `" . DB::USERS . "` WHERE id>0 ");
            $total_pages  = $result->num_rows;

            //NORMAL QUERY
            // echo "SELECT * FROM `" . DB::USERS . "` WHERE id>0 " . $search_query . " ORDER BY id DESC LIMIT $start, $limit";
            $result = $mysqli->query("SELECT * FROM `" . DB::USERS . "` WHERE id>0 " . $search_query . " ORDER BY id DESC LIMIT $start, $limit");

            while ($row = $result->fetch_array()) {

                $id                         = $row['id'];
                $photo                         = $row['photo'];
                $driver_name                   = $row['full_name'];
                $vehicle_id                   = $row['vehicle_id'];
                $driver_vehicle_type        = $row['vehicle_type'] ?? '';
                $contact1                   = $row['contact1'] ?? '';
                $is_fulltime                = $row['is_fulltime'] ?? 0;

                //////////////////////////////////////////////////
            ?>

                <div class="col-lg-2">
                    <div class="card">
                        <div class="card-img-actions">

                            <!-- Light Box -->
                            <?php if (!empty($photo) && file_exists('../uploads/drivers/thumbs/' . $photo)) { ?>
                                <a data-lightbox="driver" href="../uploads/drivers/<?php echo $photo; ?>" target="_blank">
                                    <img class="card-img-top img-fluid" src="../uploads/drivers/thumbs/<?php echo $photo; ?>" alt="">
                                </a>
                            <?php } else { ?>
                                <img class="card-img-top img-fluid" src="../images/no-image.png" alt="" />

                            <?php } ?>
                        </div>

                        <?php //$vehicle_id = getTableAttrv('id', tbl_vehicles, "driver_id = $id");
                        ?>

                        <div class="card-body">
                            <h6 class="mb-0">
                                <a href="driver.php?id=<?php echo $id; ?>"><?php echo $driver_name; ?></a>

                                <!-- <a data-bs-toggle="modal" data-bs-target="#delete2Modal" onclick="confirmDelete2Modal('<?php echo $driver_name; ?>', '<?php echo $id; ?>')">
										<i class="ph-car-simple"></i>
									</a> -->
                                <!-- <a href="driver.php?id=<?php echo $id; ?>">
										<i class="ph-user-circle"></i>
									</a> -->

                                <?php if (!empty($vehicle_id)) { ?>
                                    <a href="driver.php?id=<?php echo $id; ?>&selected_tab=vehicle" data-bs-popup="tooltip" data-bs-original-title="Vehicle Details">
                                        <i class="ph-car-simple"></i>
                                    </a>
                                <?php } ?>

                                <a href="driver.php?id=<?php echo $id; ?>&selected_tab=booking" data-bs-popup="tooltip" data-bs-original-title="Booking History">
                                    <i class="ph-map-pin-line"></i>
                                </a>

                                <a href="driver.php?id=<?php echo $id; ?>&selected_tab=cash" data-bs-popup="tooltip" data-bs-original-title="Cash Settlement">
                                    <i class="ph-currency-circle-dollar"></i>
                                </a>

                            </h6>
                            <span class="text-muted"><?php echo $contact1; ?></span>

                            <br /><small class="mb-0">[<?php echo (($is_fulltime == 1) ? 'Full Time' : 'Is Freelance'); ?>]</small><br /><br />

                            <?php
                            if (!empty($vehicle_id)) {

                                $make             = getTableAttr('make', tbl_vehicles, $vehicle_id);
                                $number_plate     = getTableAttr('number_plate', tbl_vehicles, $vehicle_id);
                                $model             = getTableAttr('model', tbl_vehicles, $vehicle_id);
                                // $vehicle_type 	= getTableAttr('vehicle_type', tbl_vehicles, $vehicle_id);
                            ?>

                                <div class="text-muted">
                                    <?php echo $make; ?>
                                    <?php echo ((!empty($number_plate)) ? $number_plate : '') ?>
                                    <?php echo ((!empty($model)) ? '[' . $model . ']' : '') ?>
                                </div>
                            <?php } ?>




                        </div>

                        <?php if (granted_('assign_vehicle', 'drivers')) { ?>
                            <div class="card-footer d-flex justify-content-between">

                                <?php

                                $driver_vehicle_type_id         = (int)($driver_vehicle_type ?: 0);
                                $driver_vehicle_type_caption     = ($driver_vehicle_type_id > 0) ? getTableAttr('vehicle_type', tbl_vehicle_types, $driver_vehicle_type_id) : '';

                                if (empty($vehicle_id)) {
                                ?>
                                    <?php echo $driver_vehicle_type_caption; ?> <br />
                                    <a data-bs-toggle="modal" data-bs-target="#assignVehicleModal" onclick="confirmAssignVehicleModal('<?php echo $driver_name; ?>', '<?php echo $id; ?>', '<?php echo $driver_vehicle_type_id; ?>', '<?php echo $driver_vehicle_type_caption; ?>')" data-bs-popup="tooltip" data-bs-original-title="Assign Driver">
                                        <i class="ph-car"></i> Assign
                                    </a>

                                <?php } else { ?>
                                    <?php echo $driver_vehicle_type_caption; ?>
                                    <a data-bs-toggle="modal" data-bs-target="#unAssignVehicleModal" onclick="confirmUnAssignVehicleModal('<?php echo $driver_name; ?>', '<?php echo $id; ?>')" data-bs-popup="tooltip" data-bs-original-title="Un-Assign Driver">
                                        <i class="ph-car"></i> Un-Assign
                                    </a>
                                <?php } ?>

                                <!-- <span class="text-muted">Updated: Apr 25th</span>
							<ul class="list-inline mb-0">
								<li class="list-inline-item"><a href="#">Edit</a></li>
								<li class="list-inline-item"><a href="#">Delete</a></li>
							</ul> -->

                            </div>
                        <?php } ?>



                    </div>
                </div>

            <?php } //while
            ?>
        </div>

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



        <!-- <div class="card">
			<div class="content clearfix">
				<div class="table-responsive">
<table id="grid-<?php echo $module; ?>" class="custom_datatables display responsive no-wrap table-hover" width="100%">
					<thead>
						<tr>
							<th width="40">SR.</th>
							<th width="160">IMAGE</th>
							<th>NAEM</th>
							<th>EMAIL</th>
							<th>CONTACT 1</th>
							<th>CONTACT 2</th>
							<th width="90">CREATED AT</th>
							<th width="50">STATUS</th>
							<th width="40">ACTION</th>
						</tr>
					</thead>
				</table>
</div>
			</div>
		</div> -->

    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
