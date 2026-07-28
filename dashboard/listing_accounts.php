<?php

use App\Core\DB;
include('admin_elements/admin_header.php');
$module = 'accounts';
$module_caption = 'Account';
$tbl_name = DB::ACCOUNTS;
$error_message = '';
$success_message = '';

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
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

$type = 'Assets';

if (isset($_REQUEST['type']) && !empty($_REQUEST['type'])) {
	$type            = e_s__($_REQUEST['type']);
}



/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
|
*/
// 	if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {

// 	if (is_SuperAdmin()) {

// 		$mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id");
// 	} else {

// 		$mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND created_by ='" . Session::userId() . "'");
// 	}


// 	if ($mysqli->affected_rows > 0) {
// 		$success_message = "Item deleted successfully.";
// 		header("Location:listing_$module.php?success_message=$success_message");
// 	} else {
// 		$error_message = "Action denied. You are not authorized to delete this record.";
// 	}
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
		<div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
			<div class="my-1">
				<h5 class="mb-0">Chart of Accounts</h5>
			</div>

			<?php if (isset($module_id) && granted('create', $module_id)) { ?>
				<div class="my-1">
					<button type="button" class="btn btn-primary btn-sm" onclick="window.location.href='<?php echo $module; ?>.php';"><i class="ph-plus ph-sm me-2 opacity-75"></i>New</button>
				</div>
			<?php } ?>
		</div>
	</div>
	<!-- /page header -->

	<div class="content-inner">
		<div class="content">

			<?php include('admin_elements/breadcrumb.php'); ?>

			<div class="col-xl-12">
				<div class="row mb-2">
					<div class="col-xl-2"></div>

					<div class="col-xl-8">

						<ul class="nav nav-pills nav-pills-outline nav-justified">

							<?php
							// ======================================================
							$result = $mysqli->query("SELECT * FROM `" . DB::ACCOUNTS . "` WHERE parent_id IS NULL ORDER BY FIELD(account_type, 'Assets', 'Expense', 'Liability', 'Income', 'Equity') LIMIT 5 ");
							while ($rows = $result->fetch_array()) {
								$account_type = $rows['account_type']
								// ======================================================
							?>

								<li class="nav-item">
									<a href="listing_accounts.php?type=<?php echo $account_type; ?>" class="nav-link bg-success text-white <?php if ($account_type == $type) { ?> bg-info <?php } ?> ?>">
										<?php echo $rows['account_name']; ?>

										<span class="badge bg-success rounded ms-auto">
											<?php
											// ----------------------------------------------------------------------------------------------
											$rs = $mysqli->query("SELECT * FROM `" . DB::ACCOUNTS . "` WHERE account_type='" . $account_type . "'");
											$total_accounts = $rs->num_rows;
											echo $total_accounts;
											// ----------------------------------------------------------------------------------------------
											?>
										</span>
									</a>
								</li>

							<?php } // while 
							?>

						</ul>
					</div>

					<div class="col-xl-2"></div>
				</div>
			</div>


			<div class="col-xl-12">
				<div class="row mb-2">
					<div class="col-xl-2"></div>
					<div class="col-xl-8">

						<div class="card">
							<div class="card-header d-flex align-items-center">
								<h5 class="mb-0"><?php echo $type; ?></h5>

								<!-- <div class="ms-auto">
									<a href="listing_quotations.php">0</a>
								</div> -->
							</div>

							<div class="card-body">
								<!-- <p class="mb-3">Example of a table placed inside <code>card body</code>. Such tables always have additional whitespace taken from <code>.card-body</code> element padding.</p> -->

								<div class="table-responsive">
									<div class="table-responsive">
<table class="table">
										<!-- <thead>
											<tr>
												<th>Last Name</th>
												<th>Username</th>
											</tr>
										</thead> -->
										<tbody>

											<?php
											// Recursive function to print accounts and their children
											function renderAccounts($mysqli, $parentId = null, $level = 0, $type = null)
											{
												global $module_id;
												// $indent = '';
												// if ($level > 2){
												$indent = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $level); // indentation for nested levels
												// }

												// Build query
												if ($parentId === null) {
													// fetch top-level accounts
													$query = "SELECT *  FROM `" . DB::ACCOUNTS . "`  WHERE account_type='" . $type . "' AND parent_id IS NULL ORDER BY account_name";
												} else {
													// fetch children
													$query = "SELECT * FROM `" . DB::ACCOUNTS . "`  WHERE parent_id='" . $parentId . "'  ORDER BY account_name";
												}

												$result = $mysqli->query($query);

												while ($row = $result->fetch_array()) {
													$id            	= $row['id'];
													$parent_id     	= $row['parent_id'];
													$level     		= $row['level'];
													$account_name  	= $row['account_name'];
													$description   	= $row['description'];

													// List of system accounts that should not be editable
													$system_accounts = array(
														'Dividend', 'Drawing', 'Distribution', 'Retained Earnings',
														'Opening Balance', 'Suspense', 'Clearing', 'Rounding',
														'Currency Gain', 'Currency Loss', 'Variance', 'Adjustment'
													);
													
													// Check if this account is a system account (contains any system keywords)
													$is_system_account = false;
													foreach ($system_accounts as $sys_account) {
														if (stripos($account_name, $sys_account) !== false) {
															$is_system_account = true;
															break;
														}
													}
													
													// Check if this account is a parent account (has children)
													$is_parent_account = false;
													$child_count_query = "SELECT COUNT(*) as child_count FROM `" . DB::ACCOUNTS . "` WHERE parent_id = '" . $id . "'";
													$child_count_result = $mysqli->query($child_count_query);
													if ($child_count_result && $child_count_result->num_rows > 0) {
														$child_count_row = $child_count_result->fetch_array(MYSQLI_ASSOC);
														if ($child_count_row['child_count'] > 0) {
															$is_parent_account = true;
														}
													}

													if ($parent_id != NULL) {

														$bold = (($level == 2) ? "class=\"fw-semibold\"" : '');

														// Get last transaction date for this account
														// $last_transaction_date = 'No transactions';
														$last_transaction_date = '';
														$has_transactions = false;
														$txn_query = "SELECT MAX(j.journal_date) AS last_date, COUNT(ji.id) as txn_count
															FROM `" . DB::JOURNAL_ITEMS . "` ji
															LEFT JOIN `" . DB::JOURNALS . "` j ON j.id = ji.journal_id
															WHERE ji.account = '" . $id . "'";
														$txn_result = $mysqli->query($txn_query);
														if ($txn_result && $txn_result->num_rows > 0) {
															$txn_row = $txn_result->fetch_array(MYSQLI_ASSOC);
															if (!empty($txn_row['last_date']) && $txn_row['last_date'] != '0000-00-00') {
																$last_transaction_date = 'Last transaction on ' . processDateYtoD($txn_row['last_date']);
																$has_transactions = true;
															}
														}

														echo "<tr>";
														echo "<td $bold>" . $indent . $account_name . "<br />
														<span class=\"text-muted small\">" . $indent . " &nbsp;" . $last_transaction_date . "</span></td>";
														echo "<td>" . $description . "</td>";
														
														// Show edit icon only for non-system accounts with no transactions, level > 1, and not a parent account
														echo "<td>";
														if ($is_system_account) {
															// System account - show lock icon
														echo "<span class=\"text-danger\" title=\"System account - cannot be edited\"><i class=\"ph-lock\"></i></span>";
														} else if ($is_parent_account) {
															// Parent account with children - show lock icon
												echo "<span class=\"text-danger\" title=\"Parent account with sub-accounts - cannot be edited\"><i class=\"ph-lock\"></i></span>";
														} else if ($level > 1 && !$has_transactions && granted('edit', $module_id)) {
															// Regular account - editable
															echo "<a href='accounts.php?action=edit_accounts&id=$id'><span class=\"text-dark opacity-50\"><i class=\"ph-pencil\"></i></span></a>";
														} else if ($has_transactions) {
															// Account with transactions - locked
												echo "<span class=\"text-danger\" title=\"Cannot edit accounts with transactions\"><i class=\"ph-lock\"></i></span>";
														}
														echo "</td>";
														
														echo "</tr>";
													}

													// 🔄 Recursive call to get children of this account
													renderAccounts($mysqli, $id, $level + 1, $type);
												}
											}
											?>

											<!-- Usage -->
											<div class="table-responsive">
<table class="table">
												<thead>
													<tr>
														<th>Account Name</th>
														<th>Description</th>
														<th>Edit</th>
													</tr>
												</thead>
												<tbody>
													<?php renderAccounts($mysqli, null, 0, $type); ?>
												</tbody>
											</table>
</div>
</div>


										</tbody>
									</table>
								</div>
							</div>
						</div>

					</div>
					<div class="col-xl-2"></div>
				</div>
			</div>


			<!-- /content area -->

		</div>
		<?php include('admin_elements/copyright.php'); ?>
	</div>
	<!-- /inner content -->
</div>

</div>

<?php include('admin_elements/admin_footer.php'); ?>