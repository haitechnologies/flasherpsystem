<?php

use App\Core\DB;

include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'journals';
$module_caption = 'Client';
$tbl_name = $tbl_prefix . $module;
$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');


$limit 				= 10;
$stages 			= 2;

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


if (isset($_REQUEST['export']) && !empty($_REQUEST['export']))	$export     = e_s__($_REQUEST['export']);
else $export = '';


/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/
// if ($action == "generate_report") {
$date_from                  = (isset($_REQUEST['date_from']) ? e_s__($_REQUEST['date_from']) : '');
$date_to                    = (isset($_REQUEST['date_to']) ? e_s__($_REQUEST['date_to']) : '');
$client_id                  = (isset($_REQUEST['client_id']) ? e_s__($_REQUEST['client_id']) : '');
$agent_id                   = (isset($_REQUEST['agent_id']) ? e_s__($_REQUEST['agent_id']) : '');
// }




/*
|--------------------------------------------------------------------------
| 	PAGINATION
|--------------------------------------------------------------------------
|
*/

if (isset($_GET['page_no']) && !empty($_GET['page_no'])) {
	$page_no            = e_s__($_GET['page_no']);
} else {
	$page_no            = 1;
}


$targetpage			= 'report_clients.php?action=generate_report&date_from=' . $date_from . '&date_to=' . $date_to . '&client_id=' . $client_id . '&agent_id=' . $agent_id;



if ($page_no) {
	$start   = ($page_no - 1) * $limit;
} else {
	$start   = 0;
}

// -------------------
$search_query = '';
// -------------------



// if (!empty($date_from) && !empty($date_to)) {
// 	// $search_query .= " AND created_at BETWEEN '" . processDateDtoY($date_from) . "' AND '" . processDateDtoY($date_to) . "'";
// 	$search_query .= " AND booking_id IN (SELECT id FROM " . tbl_bookings . " WHERE booking_date BETWEEN '" . processDateDtoY($date_from) . "' AND '" . processDateDtoY($date_to) . "' ) ";
// }

// if (!empty($date_from)) {
// 	// $search_query .= " AND created_at >= '" . processDateDtoY($date_from) . "'";
// 	$search_query .= " AND booking_id IN (SELECT id FROM " . tbl_bookings . " WHERE booking_date >= '" . processDateDtoY($date_from) . "') ";
// }

// if (!empty($date_to)) {
// 	// $search_query .= " AND created_at <= '" . processDateDtoY($date_to) . "'";
// 	$search_query .= " AND booking_id IN (SELECT id FROM " . tbl_bookings . " WHERE booking_date <= '" . processDateDtoY($date_to) . "') ";
// }

// if (!empty($client_id)) {
// 	$search_query .= " AND booking_id IN (SELECT id FROM " . tbl_bookings . " WHERE client_id = $client_id) ";
// }

// if (!empty($agent_id)) {
// 	$search_query .= " AND booking_id IN (SELECT id FROM " . tbl_bookings . " WHERE agent_id = $agent_id) ";
// }


// ----------------------------------------------------------------------------------------------------


/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/

if ($action == "generate_report") {

	//COUNT QUERY
	$result 		= $mysqli->query("SELECT id FROM `" . DB::CUSTOMERS . "` WHERE id>0 " . $search_query);
	$total_pages  	= $result->num_rows;

	//NORMAL QUERY
	// $result_clients = $mysqli->query("SELECT * FROM `" . DB::CUSTOMERS . "` WHERE id>0 " . $search_query . " ORDER BY id DESC LIMIT $start, $limit");
	// echo "SELECT * FROM `" . DB::CUSTOMERS . "` WHERE id>0 " . $search_query . " ORDER BY id DESC LIMIT $start, $limit";
	$result_clients = $mysqli->query("SELECT * FROM `" . DB::CUSTOMERS . "` WHERE id>0 " . $search_query . " ORDER BY id ASC LIMIT $start, $limit");

	//EXPORT EXCEL
	// $result_clients_ = $mysqli->query("SELECT * FROM `" . DB::CUSTOMERS . "` WHERE id>0 " . $search_query . " ORDER BY id ASC LIMIT $start, $limit");
	$result_clients_ = $mysqli->query("SELECT * FROM `" . DB::CUSTOMERS . "` WHERE id>0 " . $search_query . " ORDER BY id ASC"); // Remove Limit
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
					<a href="listing_clients.php" class="breadcrumb-item">Clients</a>
					<span class="breadcrumb-item active">Generate Report</span>
				</div>

				<a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
					<i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
				</a>
			</div>

			<!-- <div class="collapse d-lg-block ms-lg-auto" id="breadcrumb_elements">
				<div class="d-lg-flex mb-2 mb-lg-0">
					<button type="button" onclick="window.location.href='index.php';" class=" btn btn-outline-primary my-1 me-2">Exit</button>
				</div>
			</div> -->

		</div>
	</div>
	<!-- /page header -->


	<div class="content">

		<?php include('admin_elements/breadcrumb.php'); ?>

		<div class="row">
			<div class="col-lg-12">
				<div class="card bg-success bg-opacity-10">
					<!-- <div class="card-header">
                            <h6 class="mb-0">Generate Invoice For:</h6>
                        </div> -->

					<form class="steps-basic clearfix" method="request" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="report_clients.php">
						<input type="hidden" name="action" id="action" value="generate_report" />

						<div class="card-body">

							<div class="row">

								<div class="col-lg-2">
									<div class="mb-3">
										<label class="form-label fw-semibold">Date From: </label>

										<div class="form-control-feedback form-control-feedback-start">
											<input type="text" class="form-control" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
											<div class="form-control-feedback-icon">
												<i class="ph-calendar"></i>
											</div>
										</div>

									</div>
								</div>

								<div class="col-lg-2">
									<div class="mb-3">
										<label class="form-label fw-semibold">Date To: </label>

										<div class="form-control-feedback form-control-feedback-start">
											<input type="text" class="form-control" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
											<div class="form-control-feedback-icon">
												<i class="ph-calendar"></i>
											</div>
										</div>

									</div>
								</div>


								<div class="col-lg-2">
									<div class="mb-3">
										<label class="form-label fw-semibold">Client: </label>

										<select name="client_id" id="client_id" class="form-control" onchange="ajax_populate_properties(this.value);">
											<option value='0'>Please select</option>
											<?php

											$client_details = '';

											$result = $mysqli->query("SELECT * FROM `" . DB::CUSTOMERS  . "` WHERE is_active=1 ORDER BY id DESC");
											while ($rows = $result->fetch_array()) {

												$title                = $rows["title"];
												$first_name           = $rows["first_name"];
												$last_name            = $rows["last_name"];
												$company_name         = $rows["company_name"];
												$company_is_primary   = $rows["company_is_primary"];

												if ($company_is_primary == 1) {
													$client_details =  $company_name . ' - ' . ucwords($title) . ' ' . $first_name . ' ' . $last_name;
												} else {
													$client_details =  ucwords($title) . ' ' . $first_name . ' ' . $last_name . ' - ' . $company_name;
												}
											?>
												<option value="<?php echo $rows['id']; ?>" <?php if ($action == "edit_$module" && $rows['id'] == $client_id) { ?>selected <?php } else if ($rows['id'] == $client_id) { ?>selected <?php } ?>>
													<?php echo $client_details; ?>
												</option>
											<?php } ?>
										</select>

									</div>
								</div>


								<div class="col-lg-2">
									<div class="mb-3">
										<label class="form-label fw-semibold">Property: </label>

										<select class="form-select" name="property_id" id="property_id">
											<option value='0'>Please select</option>
											<?php
											if (!empty($client_id)) {
												$result_properties = $mysqli->query("SELECT * FROM `" . tbl_properties  . "` WHERE is_active=1 AND client_id=$client_id");
											} else {
												$result_properties = $mysqli->query("SELECT * FROM `" . tbl_properties  . "` WHERE id=0");
											}


											while ($rows_properties = $result_properties->fetch_array()) {

												$street1        = s__($rows_properties['street1']);

												$city            = s__($rows_properties['city']);
												$city           =  getTableAttr('city', tbl_geo_cities, $city);

												$country        = s__($rows_properties['country']);
												$country        =  getTableAttr('country', tbl_geo_countries, $country);
											?>

												<option value="<?php echo $rows_properties['id']; ?>" <?php if ($action == "edit_$module" && $rows_properties['id'] == $property_id) { ?>selected <?php } else if ($rows_properties['id'] == $property_id) { ?>selected <?php } ?>>
													<?php echo $street1; ?> - <?php echo $city; ?> - <?php echo $country; ?>
												</option>

											<?php
											}  // while
											?>
										</select>
									</div>
								</div>


								<div class="col-lg-3">
									<div class="mt-3">

										<button type="submit" class="btn btn-success my-1 me-2">Generate Report</button>
										<button type="button" onclick="resetForm();" class="btn btn-light my-1 me-2">Reset</button>

									</div>
								</div>


							</div>

						</div>

					</form>

				</div>
			</div>

		</div>

		<script>
			function resetForm() {
				document.getElementById('date_from').value = '';
				document.getElementById('date_to').value = '';
				document.getElementById('client_id').value = '0';
				document.getElementById('client_id').text = 'Please select';
				document.getElementById('agent_id').value = '0';
				document.getElementById('agent_id').text = 'Please select';
			}
		</script>


		<?php
		if ($action == 'generate_report') {
		?>

			<div class="card pb-3">
				<div class="card-header d-flex align-items-center">
					<h5 class="mb-0"><?php echo $total_pages; ?> Clients Found.</h5>

					<div class="ms-auto">

						<!-- <a href="export_pdf.php?<?php echo str_replace('listing_reports.php?', '', $targetpage); ?>&export=pdf" target="_blank">
							<button type="button" class="btn btn-info btn-labeled btn-labeled-start">
								<span class="btn-labeled-icon bg-black bg-opacity-20">
									<i class="ph-file-pdf"></i>
								</span>Export PDF
							</button>
						</a> -->

						<!-- <a href="<?php echo $targetpage; ?>&export=excel">
							<button type="button" class="btn btn-info btn-labeled btn-labeled-start">
								<span class="btn-labeled-icon bg-black bg-opacity-20">
									<i class="ph-file-csv"></i>
								</span>Export Excel
							</button>
						</a> -->

					</div>
				</div>

				<div class="table-responsive">
					<table class="table">

						<thead>
							<tr>
								<th width="40">SR.</th>
								<th>IMAGE</th>
								<th>NAME</th>
								<th>ADDRESS</th>
								<th>TAGS</th>
								<th>STATUS</th>
								<th width="90">CREATED AT</th>
								<th width="50">STATUS</th>
							</tr>
						</thead>


						<tbody>

							<?php

							// ---------------------------------------------------------------------------------------
							while ($row_clients = $result_clients->fetch_array(MYSQLI_ASSOC)) {

								$id                   = $row_clients["id"];
								$photo                = $row_clients["photo"];

								$title                = $row_clients["title"];
								$first_name           = $row_clients["first_name"];
								$last_name            = $row_clients["last_name"];
								$company_name         = $row_clients["company_name"];
								$company_is_primary   = $row_clients["company_is_primary"];
								$trn                  = $row_clients["trn"];

								$publish              = $row_clients["is_active"];
								$created_at           = $row_clients["created_at"];

								// Light Box 
								if (!empty($photo) && file_exists('../uploads/clients/thumbs/' . $photo)) {
									$display_photo      = display_photo($photo, 'clients', $width = 50, $height = 50);
									$photo_upload_path  = '../uploads/' . $module;
									$nestedData[]       = '<a data-lightbox="' . $id . '" href=" ' . $photo_upload_path . '/' . $photo . ' " target="_blank">' . $display_photo . '</a>';
								} else {
									$nestedData[] = '<img src="../images/no-image-50.png" alt="" />';
								}



								if ($company_is_primary == 1) {
									$client_details = '<strong>' . $company_name . '</strong><br />' . ucwords($title) . ' ' . $first_name . ' ' . $last_name;
								} else {
									$client_details = '<strong>' . ucwords($title) . ' ' . $first_name . ' ' . $last_name . '</strong><br />' . $company_name;
								}


								// ------------------------------------------------------------------------------------------------
								$result_property  = $mysqli->query("SELECT id FROM `" . tbl_properties . "` WHERE client_id=$id");
								if ($result_property->num_rows > 1) {
									$address =  $result_property->num_rows . ' properties';
								} else {
									$result_property  = $mysqli->query("SELECT * FROM `" . tbl_properties . "` WHERE client_id=$id AND is_primary=1");
									$row_property     = $result_property->fetch_array();
									$property_id      = s__($row_property['id']);

									$street1          = s__($row_property['street1']);

									$city             = s__($row_property['city']);
									$city             =  getTableAttr('city', tbl_geo_cities, $city);

									$country          = s__($row_property['country']);
									$country          =  getTableAttr('country', tbl_geo_countries, $country);

									$address = $street1 . '  ' . $city . '  ' . $country;
								}
								// ---------------------------------------------------------------------------------------

								if ($publish == 0)
									$status = '<span class="badge bg-warning">InActive</span>';
								else
									$status = '<span class="badge bg-success">Active</span>';


								// ---------------------------------------------------------------------------------------
							?>

								<tr>
									<td width="100"><?php echo $id; ?></td>
									<td width="100"></td>
									<td><?php echo $client_details; ?></td>

									<td><a href="view_client.php?id=<?php echo $client_id; ?>"><a href="view_client.php?id=' . $id . '" class=" text-black"> <?php echo $address; ?> </a></td>
									<td><a href="view_client.php?id=<?php echo $client_id; ?>"></td>
									<td><a href="view_client.php?id=<?php echo $client_id; ?>"></td>

									<td><?php echo $status; ?></td>

								</tr>


							<?php
							} //while
							?>


						</tbody>
					</table>
				</div>
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

			$lastpage 	= ceil($total_pages / $limit);
			$LastPagem1 = $lastpage - 1;

			$pagination = '';

			if ($lastpage > 1) {
				$pagination .= '<div class="center-block text-center">';
				$pagination .= '<ul class="pagination mb-5 mb-lg-0">';

				// PREVIOUS
				if ($page_no > 1) {
					$pagination	.= '<li class="page-item page-prev"><a class="page-link" href="' . $targetpage . '&page_no=' . $prev . '" tabindex="-1">Prev</a></li>';
				} else {
					$pagination	.= '<li class="page-item page-prev disabled"><a class="page-link" href="#" tabindex="-1">Prev</a></li>';
				}

				// Pages
				if ($lastpage < 7 + ($stages * 2))	// Not enough pages to breaking it up
				{
					for ($counter = 1; $counter <= $lastpage; $counter++) {
						if ($counter == $page_no) {
							$pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
						} else {
							$pagination	.= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $counter . "'>" . $counter . "</a></li>";
						}
					}
				} else if ($lastpage > 5 + ($stages * 2))	// Enough pages to hide a few?
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
		} // endif
?>
</div>
<?php include('admin_elements/copyright.php'); ?>
</div>

<?php include('admin_elements/admin_footer.php'); ?>