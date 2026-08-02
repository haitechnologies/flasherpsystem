<?php


use App\Core\DB;
include('admin_elements/admin_header.php');

$module = 'customers';
$module_caption = 'Customer';
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

$activeOrganizationId = dashboardRequireActiveOrganization();


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

$customer_id = '';
if (isset($_REQUEST['customer_id']))        $customer_id     = e_s__($_REQUEST['customer_id']);
if (isset($_POST['customer_id']))           $customer_id     = e_s__($_POST['customer_id']);



//VERIFY IF IS VALID 
$rs_customer_valid  = $mysqli->query("SELECT id FROM `" . DB::CUSTOMERS . "` WHERE id='" . $customer_id . "'");
if ($rs_customer_valid->num_rows == 0) header("Location:listing_customers.php");



/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
|
*/
$customer_name = '';

if (!empty($id)) {

    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$id");
    $row = $result->fetch_array();

    $customer_owner             = s__($row['customer_owner']);
    $customer_owner             = getTableAttr('full_name', DB::USERS, $customer_owner);

    $payment_term               = s__($row['payment_term']);

    $customer_status            = s__($row['customer_status']);
    $customer_status            = getTableAttr('value', DB::TAXONOMIES, $customer_status);

    $customer_source            = s__($row['customer_source']);
    $customer_source            = getTableAttr('value', DB::TAXONOMIES, $customer_source);

    $assigned_to                = s__($row['assigned_to']);
    $assigned_to                = getTableAttr('full_name', DB::USERS, $assigned_to);

    $salutation                 = ((!empty($row['salutation']) ? ucwords(s__($row['salutation'])) : ''));
    $first_name                 = s__($row['first_name']);
    $last_name                  = s__($row['last_name']);
    // $company_name               = s__($row['company_name']);
    $display_name               = s__($row['display_name']);
    $address                    = s__($row['address']);
    $email                      = s__($row['email']);
    $phone                      = s__($row['phone']);
    $mobile                     = s__($row['mobile']);

    $tax_treatment          = s__($row['tax_treatment']);
    $trn                    = s__($row['trn']);
    $license_number         = s__($row['license_number']);
    $license_expiry         = s__($row['license_expiry']);
    $license_expiry         = ($license_expiry == '1970-01-01' ? '' : processDateYtoD($license_expiry));

    $currency               = s__($row['currency']);
    $currency               = getTableAttr("currency", DB::CURRENCIES, $currency);
    $exchange_rate          = s__($row['exchange_rate']);

    $sales_person           = s__($row['sales_person']);
    $sales_person           = getTableAttr("full_name", DB::USERS, $sales_person);

    $cs_agent               = s__($row['cs_agent']);
    $cs_agent               = getTableAttr("department", DB::DEPARTMENTS, $cs_agent);

    $lead_category          = s__($row['lead_category']);
    $rating                 = s__($row['rating']);

    $contacted_date         = s__($row['contacted_date']);
    $contacted_date         = ($contacted_date == '1970-01-01 00:00:00' ? '' : dd_($contacted_date, 'd M Y g:ia'));

    $description                = s__($row['description']);

    // -- Tags
    $tags                   = s__($row['tags']);
    $tags_arr               = array();
    $tags_captions = '';

    if ($tags != NULL) {
        $tags_arr               = explode(',', $tags);

        // $tags_captions = '';

        foreach ($tags_arr as $tag_id) {
            $tags_captions .= '<span class="badge bg-light text-dark">' . getTableAttr('value', DB::TAXONOMIES, $tag_id) . '</span> &nbsp;';
        }
    }

    $website                = s__($row['website']);
    $department             = s__($row['department']);
    $designation            = s__($row['designation']);
    $x                      = s__($row['x']);
    $facebook               = s__($row['facebook']);
    $instagram              = s__($row['instagram']);


    $approved               = s__($row['approved']);
    $approved_by            = s__($row['approved_by']);
    $approved_at            = s__($row['approved_at']);

    $is_active = s__($row['is_active']);
    $created_at             = s__($row['created_at']);
    $created_by             = s__($row['created_by']);
}

// $photo = getTableAttr('photo', $tbl_name, $id);
/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/
?>


<style>
    /* .timeline {
        position: relative;
        padding: 0;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 50%;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #ddd;
        transform: translateX(-50%);
    }

    .timeline-item {
        display: flex;
        align-items: flex-start;
        position: relative;
        margin-bottom: 30px;
    }

    .timeline-date {
        width: 45%;
        text-align: right;
        padding-right: 20px;
        font-weight: 600;
        color: #555;
    }

    .timeline-marker {
        position: relative;
        z-index: 1;
        background: #fff;
        border: 2px solid #0d6efd;
        border-radius: 50%;
        width: 14px;
        height: 14px;
        margin: 0 10px;
        flex-shrink: 0;
        top: 5px;
    }

    .timeline-content {
        width: 45%;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 6px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    } */
</style>

<aside class="sidebar sidebar-secondary sidebar-expand-lg" aria-label="Secondary Navigation">

    <!-- Expand button -->
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <!-- /expand button -->


    <!-- Sidebar content -->
    <?php include('admin_elements/sidebar_customer.php'); ?>
    <!-- /sidebar content -->

</aside>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
        <?php include('admin_elements/page_header_customer.php'); ?>
        <!-- /page header -->


        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">

                <div class="col-lg-6 col-xl-12">

                    <div class="card">

                        <div class="card-body">

                            <div class="row">


                                    <div class="card">
                                        <div class="card-header d-flex align-items-center">
                                            <h5 class="mb-0">System Mails</h5>
                                            <!-- <div class="ms-auto">
                                                <a href="#" class="text-body">
                                                    <i class="ph-gear"></i>
                                                </a>
                                            </div> -->
                                        </div>

                                        <div class="card-body">
                                            <div class="d-flex align-items-start mb-3">
                                                <div class="me-3 position-relative">
                                                    <div class="bg-success bg-opacity-10 text-success lh-1 rounded-pill p-2">
                                                        <i class="ph-envelope"></i>
                                                    </div>
                                                </div>

                                                <div class="flex-fill">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div><span class="text-muted">To</span> imrangconnect@gmail.com</div>
                                                        <span class="fs-sm text-muted">21 Aug 2025 09:51 PM</span>
                                                    </div>

                                                    Quote Notification - Quote - QT-000001 is awaiting your approval
                                                </div>
                                            </div>

                                        </div>
                                    </div>
 
                            </div>

                        </div>
                    </div>

                </div>
            </div>

        </div>


    </div>
    <!-- /content area -->

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>