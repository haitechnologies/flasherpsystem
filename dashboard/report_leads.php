<?php

include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'leads';
$module_caption = 'Lead';
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


$limit 				= 50;
$stages 			= 2;

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


if (isset($_REQUEST['export']) && !empty($_REQUEST['export']))	$export     = e_s__($_REQUEST['export']);
else $export = '';


// ---------------------------------------------------------------------------------------
// --------------------------------- GET MAXIMUM DATE -----------------------------------
// ---------------------------------------------------------------------------------------
// $result_max 	= $mysqli->query("SELECT max(lead_date) FROM `" . tbl_leads . "`");
// $row_max 		= $result_max->fetch_array();
// $max_date		= $row_max[0];

// if ($max_date == '0000-00-000') $max_date = date('Y-m-d', time());

// ---------------------------------------------------------------------------------------
// --------------------------------- GET MINIMUM DATE -----------------------------------
// ---------------------------------------------------------------------------------------
// $result_min 	= $mysqli->query("SELECT min(lead_date) FROM `" . tbl_leads . "`");
// $row_min 		= $result_min->fetch_array();
// $min_date		= $row_min[0];

// if ($min_date == '0000-00-000') $min_date = date('Y-m-d', time());
// ---------------------------------------------------------------------------------------



/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/
// if ($action == "generate_report") {

$from                  		= (isset($_REQUEST['from']) ? e_s__($_REQUEST['from']) : '');
$date_from                  = (isset($_REQUEST['date_from']) ? e_s__($_REQUEST['date_from']) : '');
$date_to                    = (isset($_REQUEST['date_to']) ? e_s__($_REQUEST['date_to']) : '');
$ticket_enumber          	= (isset($_REQUEST['ticket_enumber']) ? e_s__($_REQUEST['ticket_enumber']) : '');
$booking_full_name          = (isset($_REQUEST['booking_full_name']) ? e_s__($_REQUEST['booking_full_name']) : '');
$booking_mobile             = (isset($_REQUEST['booking_mobile']) ? e_s__($_REQUEST['booking_mobile']) : '');
$is_free               		= (isset($_REQUEST['is_free']) ? e_s__($_REQUEST['is_free']) : '');
$check               		= (isset($_REQUEST['check']) ? e_s__($_REQUEST['check']) : '');
$ticket_type               	= (isset($_REQUEST['ticket_type']) ? e_s__($_REQUEST['ticket_type']) : '');
$pax               			= (isset($_REQUEST['pax']) ? e_s__($_REQUEST['pax']) : '');
$batch_id               	= (isset($_REQUEST['batch_id']) ? e_s__($_REQUEST['batch_id']) : '');
$total_cost               	= (isset($_REQUEST['total_cost']) ? e_s__($_REQUEST['total_cost']) : '');
$grand_total               	= (isset($_REQUEST['grand_total']) ? e_s__($_REQUEST['grand_total']) : '');
$booking_status             = (isset($_REQUEST['booking_status']) ? e_s__($_REQUEST['booking_status']) : '');
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


$targetpage			= 'report_leads.php?action=generate_report&date_from=' . $date_from . '&date_to=' . $date_to . '&ticket_enumber=' . $ticket_enumber . '&booking_full_name=' . $booking_full_name . '&booking_mobile=' . $booking_mobile . '&is_free=' . $is_free . '&ticket_type=' . $ticket_type . '&check=' . $check . '&pax=' . $pax . '&batch_id=' . $batch_id . '&total_cost=' . $total_cost . '&grand_total=' . $grand_total . '&booking_status=' . $booking_status;



if ($page_no) {
	$start   = ($page_no - 1) * $limit;
} else {
	$start   = 0;
}

// -------------------
$search_query = '';
// -------------------


$date_from 			= processDateDtoY($date_from);
$date_to 			= processDateDtoY($date_to);

if (!empty($date_from) && validateDate($date_from) && !empty($date_to) && validateDate($date_to)) {
	$search_query .= " AND booking_date BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
	$min_date = $date_from;
}


if (!empty($date_from) && validateDate($date_from)) {
	$search_query .= " AND booking_date >= '" . $date_from . "'";
}

if (!empty($date_to) && validateDate($date_from)) {
	$search_query .= " AND booking_date <= '" . $date_to . "'";
	$max_date = $date_to;
}

$date_from 			= processDateYtoD($date_from);
$date_to 			= processDateYtoD($date_to);

// ----------------------------------------------------------------------------------------------------




if (!empty($ticket_enumber)) {

	$search_query .= " AND id IN (SELECT booking_id FROM " . tbl_tickets . " WHERE ticket_enumber LIKE '%" . $ticket_enumber . "%') ";

	// $batch_id = getTableAttrv('batch_id', tbl_tickets, " ticket_enumber = '" . $ticket_enumber . "%' ");	
	// if (!empty($batch_id)){
	// 	$search_query .= " AND batch_id = '" . $batch_id . "%'";
	// }
}


if (!empty($booking_full_name)) {
	$search_query .= " AND booking_full_name LIKE '" . $booking_full_name . "%'";
}

if (!empty($booking_mobile)) {
	$search_query .= " AND booking_mobile LIKE '" . $booking_mobile . "%'";
}

if (!empty($is_free)) {
	$search_query .= " AND is_free = '" . $is_free . "'";
}

if (!empty($ticket_type)) {
	$search_query .= " AND ticket_type = '" . $ticket_type . "'";
}

if (!empty($check)) {

	if ($check == 'checked_in') {
		$search_query .= " AND ID IN (SELECT booking_id FROM " . tbl_tickets . " WHERE checked_in IS NOT NULL) ";
	} else if ($check == 'checked_out') {
		$search_query .= " AND ID IN (SELECT booking_id FROM " . tbl_tickets . " WHERE checked_out IS NOT NULL) ";
	}
}

if (!empty($pax)) {
	$search_query .= " AND pax = '" . $pax . "'";
}

if (!empty($batch_id)) {
	$search_query .= " AND batch_id = '" . $batch_id . "'";
}

if (!empty($total_cost)) {
	$search_query .= " AND total_cost = '" . $total_cost . "'";
}

if (!empty($grand_total)) {
	$search_query .= " AND grand_total = '" . $grand_total . "'";
}


if (!empty($booking_status)) {
	$search_query .= " AND booking_status = '" . $booking_status . "'";
}


/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/

// if ($action == "generate_report") {

//COUNT QUERY
$result 		= $mysqli->query("SELECT id FROM `" . tbl_leads . "` WHERE id>0 " . $search_query);
$total_pages  	= $result->num_rows;

//NORMAL QUERY
$result_leads 	= $mysqli->query("SELECT * FROM `" . tbl_leads . "` WHERE id>0 " . $search_query . " ORDER BY id DESC LIMIT $start, $limit");

//EXPORT EXCEL
$result_leads_ 	= $mysqli->query("SELECT * FROM `" . tbl_leads . "` WHERE id>0 " . $search_query . " ORDER BY id DESC"); // Remove Limit
// }




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
					<a href="index.php" class="breadcrumb-item">Dashboard</a>
					<a href="listing_leads.php" class="breadcrumb-item">Leads</a>
					<span class="breadcrumb-item active">Generate Report</span>
				</div>

				<a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
					<i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
				</a>
			</div>

			<!-- <div class="collapse d-lg-block ms-lg-auto" id="breadcrumb_elements">
				<div class="d-lg-flex mb-2 mb-lg-0">
					<button type="button" onclick="window.location.href='index.php';"" class=" btn btn-outline-primary my-1 me-2">Exit</button>
				</div>
			</div> -->

		</div>
	</div>
	<!-- /page header -->


	<div class="content">

		<?php include('admin_elements/breadcrumb.php'); ?>

		<div class="row">
			<div class="col-lg-12">
				<div class="card bg-info bg-opacity-10">
					<!-- <div class="card-header">
                            <h6 class="mb-0">Generate Invoice For:</h6>
                        </div> -->

					<form class="steps-basic clearfix" method="request" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_leads.php">
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

								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Checked In / Out: </label>

										<select class="form-select" name="check" id="check">
											<option value='0'>Please select</option>
											<option value="checked_in" <?php if ($check == 'checked_in') { ?> selected <?php } ?>> Checked-In </option>
											<option value="checked_out" <?php if ($check == 'checked_out') { ?> selected <?php } ?>> Checked-Out </option>
										</select>

									</div>
								</div>

								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Ticket Type: </label>

										<select class="form-select" name="ticket_type" id="ticket_type">
											<option value='0'>Please select</option>
											<option value="vvip" <?php if ($ticket_type == 'vvip') { ?> selected <?php } ?>> VVIP </option>
											<option value="vip" <?php if ($ticket_type == 'vip') { ?> selected <?php } ?>> VIP </option>
											<option value="fan" <?php if ($ticket_type == 'fan') { ?> selected <?php } ?>> Fan </option>
											<option value="regular" <?php if ($ticket_type == 'regular') { ?> selected <?php } ?>> Regular </option>
										</select>

									</div>
								</div>


								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Ticket Eumber #:</label>
										<input type="text" class="form-control" name="ticket_enumber" id="ticket_enumber" value="<?php echo $ticket_enumber; ?>">
									</div>
								</div>

								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Booking Full Name:</label>
										<input type="text" class="form-control" name="booking_full_name" id="booking_full_name" value="<?php echo $booking_full_name; ?>">
									</div>
								</div>

								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Booking Mobile:</label>
										<input type="text" class="form-control" name="booking_mobile" id="booking_mobile" value="<?php echo $booking_mobile; ?>">
									</div>
								</div>


								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Is Free: </label>

										<select class="form-select" name="is_free" id="is_free">
											<option value='0'>Please select</option>
											<option value="1" <?php if ($is_free == '1') { ?> selected <?php } ?>> Yes </option>
											<option value="0" <?php if ($is_free == '0') { ?> selected <?php } ?>> No </option>
										</select>

									</div>
								</div>


								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Attendee(s): </label>

										<select class="form-select" name="pax" id="pax">
											<option value='0'>Please select</option>
											<!-- <option value="1">1</option> -->
											<?php for ($i = 1; $i <= 100; $i++) { ?>
												<option value="<?php echo $i; ?>" <?php if ($pax == $i) { ?> selected="selected" <?php } ?>><?php echo $i; ?></option>
											<?php } ?>
										</select>
									</div>
								</div>

								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Batch ID:</label>
										<input type="number" class="form-control" name="batch_id" id="batch_id" value="<?php echo $batch_id; ?>">
									</div>
								</div>

								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Price:</label>
										<input type="number" class="form-control" name="total_cost" id="total_cost" value="<?php echo $total_cost; ?>">
									</div>
								</div>

								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Grand Total:</label>
										<input type="number" class="form-control" name="grand_total" id="grand_total" value="<?php echo $grand_total; ?>">
									</div>
								</div>


								<div class="col-lg-1">
									<div class="mb-3">
										<label class="form-label fw-semibold">Booking Status: </label>

										<select class="form-select" name="booking_status" id="booking_status">
											<option value='0'>Please select</option>
											<option value="paid" <?php if ($booking_status == 'paid') { ?> selected <?php } ?>> Paid </option>
											<option value="not_paid" <?php if ($booking_status == 'not_paid') { ?> selected <?php } ?>> Not Paid </option>
										</select>

									</div>
								</div>


								<div class="col-lg-2">
									<div class="mt-3">

										<!-- <button type="submit" class="btn btn-success my-1 me-2">Filter</button> -->

										<button type="submit" class="btn btn-info btn-labeled btn-labeled-start">
											<span class="btn-labeled-icon bg-black bg-opacity-20">
												<i class="ph-sliders"></i>
											</span> Filter
										</button>

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
				document.getElementById('ticket_enumber').value = '';
				document.getElementById('booking_full_name').value = '';
				document.getElementById('booking_mobile').value = '';
				document.getElementById('is_free').value = '0';
				document.getElementById('is_free').text = 'Please select';
				document.getElementById('ticket_type').value = '0';
				document.getElementById('ticket_type').text = 'Please select';
				document.getElementById('pax').value = '0';
				document.getElementById('pax').text = 'Please select';
				document.getElementById('batch_id').value = '';
				document.getElementById('booking_status').value = '0';
				document.getElementById('booking_status').text = 'Please select';
			}
		</script>


		<?php
		// if ($action == 'generate_report') {
		?>

		<div class="card pb-3">
			<div class="card-header d-flex align-items-center">
				<h5 class="mb-0"><?php echo $total_pages; ?> Bookings Found.</h5>

				<div class="ms-auto">

					<!-- <a href="export_pdf.php?<?php echo str_replace('listing_reports.php?', '', $targetpage); ?>&export=pdf" target="_blank">
							<button type="button" class="btn btn-info btn-labeled btn-labeled-start">
								<span class="btn-labeled-icon bg-black bg-opacity-20">
									<i class="ph-file-pdf"></i>
								</span>Export PDF
							</button>
						</a> -->

					<a href="<?php echo $targetpage; ?>&export=excel">
						<button type="button" class="btn btn-info btn-labeled btn-labeled-start">
							<span class="btn-labeled-icon bg-black bg-opacity-20">
								<i class="ph-file-csv"></i>
							</span>Export Excel
						</button>
					</a>

				</div>
			</div>

			<div class="table-responsive">
				<table class="table">

					<thead>
						<tr>
							<th width="80">SR.</th>
							<th>ORDER</th>
							<th>BOOKING DATE</th>
							<th>BOOKING FULL NAME</th>
							<th>TICKET TYPE</th>
							<th>ATTENDEES</th>
							<th>BATCH ID</th>
							<th>BATCH TICKETS</th>
							<th>PRICE</th>
							<th>GRAND TOTAL</th>
							<!-- <th width="150">BOOKING STATUS</th> -->
							<!-- <th></th> -->
							<!-- <th width="120">ACTION</th> -->
						</tr>
					</thead>


					<tbody>

						<?php

						// ---------------------------------------------------------------------------------------
						while ($row_leads = $result_leads->fetch_array(MYSQLI_ASSOC)) {

							$id                   = $row_leads["id"];

							$manual_payment       = $row_leads["manual_payment"];
							$remarks       		  = $row_leads["remarks"];

							$payment              = $row_leads["payment"];
							$orderid              = $row_leads["orderid"];
							$orderid			  = (($orderid == 0) ?  '' : 'Order ID: ' . $orderid);
							$transactionid        = $row_leads["transactionid"];
							$transactionid		  = (($transactionid == 0) ?  '' : 'Transaction ID: ' . $transactionid);


							$parent               = $row_bookings["parent"];
							$booking_date         = $row_bookings["booking_date"];
							$booking_full_name    = $row_bookings["booking_full_name"];
							$booking_mobile       = $row_bookings["booking_mobile"];
							$is_free              = $row_bookings["is_free"];
							$ticket_type          = $row_bookings["ticket_type"];
							$pax                  = $row_bookings["pax"];
							$batch_id             = $row_bookings["batch_id"];
							$batch_tickets        = $row_bookings["batch_tickets"];
							$booking_status       = $row_bookings["booking_status"];
							$total_cost           = $row_bookings["total_cost"];
							$grand_total          = $row_bookings["grand_total"];
							$publish              = $row_bookings["publish"];
							$created_at           = $row_bookings["created_at"];

							$booking_time         = date('g:i a', strtotime($created_at));


							$qrcode = '';
							$qrcode = getTableAttrv('qrcode', tbl_tickets, "booking_id = '" . $id . " ' ");

							$ticket_id         = getTableAttrv('id', tbl_tickets, "booking_id = '" . $id . " ' ");
							$ticket_enumber   = getTableAttrv('ticket_enumber', tbl_tickets, "id = '" . $ticket_id . " ' ");

							// ---------------------------------------------------------------------------------------
						?>

							<tr>
								<td><a href="view_booking.php?id=<?php echo $id; ?>"><?php echo $id; ?></a></td>
								<td>
									<a href="view_booking.php?id=<?php echo $id; ?>">

										<?php if ($is_free == 1) { ?>
											<span class="badge bg-indigo"> Free </span>
										<?php } else { ?>
											<?php echo colorfulBookingStatus($booking_status); ?>
										<?php } ?>

										<br />

										<?php if ($is_free == 0 && $manual_payment == 1) { ?>
											<span class="badge bg-info"> Manual Payment </span>
										<?php } else if ($is_free == 0 && $payment == 1) { ?>
											<span class="badge bg-info"> Online Payment </span><br />
											<?php echo $orderid; ?><br />
											<?php echo $transactionid; ?><br />
										<?php } ?>

									</a>
								</td>

								<td><a href="view_booking.php?id=<?php echo $id; ?>"><?php echo processDateYtoD($booking_date); ?> <br /><?php echo $booking_time; ?></a></td>
								<td>
									<a href="view_booking.php?id=<?php echo $id; ?>">
										<?php echo $booking_full_name; ?> <br />
										<?php echo $booking_mobile; ?>
									</a>
								</td>
								<td><a href="view_booking.php?id=<?php echo $id; ?>" class="fw-semibold"><?php echo (($ticket_type == 'vip') ? strtoupper($ticket_type) : ucwords($ticket_type)); ?></a></td>
								<td><a href="view_booking.php?id=<?php echo $id; ?>"><?php echo $pax; ?></a></td>
								<td><a href="view_booking.php?id=<?php echo $id; ?>">* <?php echo $batch_id; ?> *</a></td>
								<td><a href="view_booking.php?id=<?php echo $id; ?>"><?php echo $batch_tickets; ?></a></td>
								<td><a href="view_booking.php?id=<?php echo $id; ?>"><?php echo $total_cost . ' (JOD)'; ?></a></td>
								<td><a href="view_booking.php?id=<?php echo $id; ?>"><?php echo (($parent == 0) ? $grand_total . ' (JOD)' : ''); ?></a></td>


								<?php
								$html_time = '';

								$checked_in         	= getTableAttrv('checked_in', tbl_tickets, " ticket_enumber = '" . $ticket_enumber . " ' ");
								if (!empty($checked_in)) {
									$checked_in_time        = date('g:i a', strtotime($checked_in));
									$html_time = '<span class="badge bg-success">Checked-in @ ' . $checked_in_time . '</span>';
								}

								$checked_out         	= getTableAttrv('checked_out', tbl_tickets, " ticket_enumber = '" . $ticket_enumber . " ' ");
								if (!empty($checked_out)) {
									$checked_out_time       = date('g:i a', strtotime($checked_out));
									$html_time = '<span class="badge bg-warning">Checked-out @ ' . $checked_out_time . '</span>';
								}


								// Light Box
								if (!empty($qrcode) && file_exists('../qrcodes_tickets/' . $qrcode . '.png')) {
									$photo_upload_path = '../qrcodes_tickets/';
									echo '<td><a data-lightbox="' . $id . '" href=" ' . $photo_upload_path . '/' . $qrcode . '.png " target="_blank"> <img src="../qrcodes_tickets/' . $qrcode . '.png" width="100" alt="" /> </a> <br /> &nbsp;' . $ticket_enumber . '<br />' . $html_time . '</td>';
								} else {
									echo '<td></td>';
								}
								?>

								<!-- <td>
									<a href="bookings.php?id=<?php echo $id; ?>"><button type="button" class="btn ph-pencil btn-outline-primary my-1 me-2" title="Edit"></button></a>
								</td> -->

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
		// } // endif
		?></div>


	<?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>