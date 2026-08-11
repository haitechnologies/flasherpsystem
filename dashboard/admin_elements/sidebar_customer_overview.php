<div class="col-lg-4">

    <?php
    use App\Core\DB;
    // ----------------------------------------------------------------
    $result_contacts = $mysqli->query("SELECT * FROM `" . DB::CUSTOMER_CONTACTS . "` WHERE contactable_type='Customer' AND contactable_id=$customer_id AND is_primary=1");
    while ($rows_contacts = $result_contacts->fetch_array()) {
        // ----------------------------------------------------------------
    ?>
        <div class="d-flex align-items-start mb-3">
            <div class="me-2 position-relative">
                <div class="bg-success bg-opacity-10 text-success lh-1 rounded-pill p-2">
                    <i class="ph-user"></i>
                </div>
            </div>

            <div class="flex-fill">
                <div class="d-flex justify-content-between align-items-center">
                    <div><span class="text-muted small">
                            <?php echo $rows_contacts['first_name']; ?> <?php echo $rows_contacts['last_name']; ?></span><br />
                        <?php echo $rows_contacts['email']; ?>
                    </div>
                    <span class="fs-sm text-muted">
                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn btn-sm me-2" data-bs-toggle="dropdown">
                                <i class="ph-gear"></i>
                            </button>

                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item small" href="customer_contacts.php?action=edit_customer_contacts&customer_id=<?php echo $customer_id; ?>&contact_id=<?php echo $rows_contacts['id']; ?>">
                                    Edit
                                </a>
                                <form method="post" action="customer_contacts.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this contact?')">
                                    <input type="hidden" name="action" value="delete_customer_contacts">
                                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                                    <input type="hidden" name="contact_id" value="<?php echo $rows_contacts['id']; ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="dropdown-item small">Delete</button>
                                </form>
                            </div>
                        </div>
                    </span>
                </div>

            </div>
        </div>
    <?php } ?>

    <div class="sidebar-section">
        <div class="sidebar-section-header border-bottom">
            <span class="text-muted">ADDRESS</span>
            <div class="ms-auto">
                <a href="#address" class="text-reset" data-bs-toggle="collapse">
                    <i class="ph-caret-down collapsible-indicator"></i>
                </a>
            </div>
        </div>

        <div class="collapse show" id="address">
            <div class="sidebar-section-body">

                <div class="small mt-3"> Billing Address</div>

                <?php
                $billing_country = ((empty($billing_country)) ? '0' : $billing_country);
                $rs_billing     = $mysqli->query("SELECT * FROM `" . DB::CUSTOMER_ADDRESSES . "` WHERE addressable_type='Customer' AND addressable_id=$customer_id AND type='billing' ");
                $row_billing    = $rs_billing->fetch_array();

                // IF EXISTS - UPDATE
                /* ---------------------- QUERY ---------------------- */
                if ($rs_billing->num_rows == 0) { ?>

                    <div><span class="small text-muted"> No Billing Address</span> - <span class="small"><a href="customer_billing_addresses.php?action=edit_customer_billing_addresses&customer_id=<?php echo $customer_id; ?>">New Address</a></div>

                <?php } else {

                    $billing_attention      = (!empty($row_billing['attention']) ? s__($row_billing['attention']) : '');

                    $billing_country        = (!empty($row_billing['country']) ? s__($row_billing['country']) : '');
                    $billing_country        = (!empty($billing_country) ? getTableAttr('country_name', DB::GEO_COUNTRIES, $billing_country)  : '');

                    $billing_address_line1  = (!empty($row_billing['address_line1']) ? s__($row_billing['address_line1']) : '');
                    $billing_address_line2  = (!empty($row_billing['address_line2']) ? s__($row_billing['address_line2']) : '');
                    $billing_city           = (!empty($row_billing['city']) ? s__($row_billing['city']) : '');
                    $billing_state          = (!empty($row_billing['state']) ? s__($row_billing['state']) : '');
                    $billing_zipcode        = (!empty($row_billing['zipcode']) ? s__($row_billing['zipcode']) : '');
                    $billing_phone          = (!empty($row_billing['phone']) ? s__($row_billing['phone']) : '');
                    $billing_fax            = (!empty($row_billing['fax']) ? s__($row_billing['fax']) : '');
                ?>

                    <div class="small fw-semibold">
                        <?php echo $billing_attention; ?>

                        <a href="customer_billing_addresses.php?action=edit_customer_billing_addresses&customer_id=<?php echo $customer_id; ?>" title="Edit">
                            <span class="text-dark opacity-50 me-1">
                                <i class="ph-pencil"></i>
                            </span>
                        </a>
                    </div>
                    <div class="small"><?php echo $billing_address_line1; ?></div>
                    <div class="small"><?php echo $billing_address_line2; ?></div>
                    <div class="small"><?php echo $billing_city; ?> <?php echo $billing_zipcode; ?></div>
                    <div class="small"><?php echo $billing_state; ?></div>
                    <div class="small"><?php echo $billing_country; ?></div>
                    <div class="small"><?php echo $billing_phone; ?></div>
                    <div class="small"><?php echo $billing_fax; ?></div>
                <?php } ?>


                <div class="small mt-3"> Shipping Address</div>

                <?php
                $shipping_country = ((empty($shipping_country)) ? '0' : $shipping_country);
                $rs_shipping     = $mysqli->query("SELECT * FROM `" . DB::CUSTOMER_ADDRESSES . "` WHERE addressable_type='Customer' AND addressable_id=$customer_id AND type='shipping' ");
                $row_shipping    = $rs_shipping->fetch_array();

                // IF EXISTS - UPDATE
                /* ---------------------- QUERY ---------------------- */
                if ($rs_shipping->num_rows == 0) { ?>

                    <div><span class="small text-muted"> No Shipping Address</span> - <span class="small"><a href="customer_shipping_addresses.php?action=edit_customer_shipping_addresses&customer_id=<?php echo $customer_id; ?>">New Address</a></div>

                <?php } else {

                    $shipping_attention      = (!empty($row_shipping['attention']) ? s__($row_shipping['attention']) : '');

                    $shipping_country        = (!empty($row_shipping['country']) ? s__($row_shipping['country']) : '');
                    $shipping_country        = (!empty($shipping_country) ? getTableAttr('country_name', DB::GEO_COUNTRIES, $shipping_country)  : '');

                    $shipping_address_line1  = (!empty($row_shipping['address_line1']) ? s__($row_shipping['address_line1']) : '');
                    $shipping_address_line2  = (!empty($row_shipping['address_line2']) ? s__($row_shipping['address_line2']) : '');
                    $shipping_city           = (!empty($row_shipping['city']) ? s__($row_shipping['city']) : '');
                    $shipping_state          = (!empty($row_shipping['state']) ? s__($row_shipping['state']) : '');
                    $shipping_zipcode        = (!empty($row_shipping['zipcode']) ? s__($row_shipping['zipcode']) : '');
                    $shipping_phone          = (!empty($row_shipping['phone']) ? s__($row_shipping['phone']) : '');
                    $shipping_fax            = (!empty($row_shipping['fax']) ? s__($row_shipping['fax']) : '');
                ?>

                    <div class="small fw-semibold">
                        <?php echo $shipping_attention; ?>

                        <a href="customer_shipping_addresses.php?action=edit_customer_shipping_addresses&customer_id=<?php echo $customer_id; ?>" title="Edit">
                            <span class="text-dark opacity-50 me-1">
                                <i class="ph-pencil"></i>
                            </span>
                        </a>
                    </div>
                    <div class="small"><?php echo $shipping_address_line1; ?></div>
                    <div class="small"><?php echo $shipping_address_line2; ?></div>
                    <div class="small"><?php echo $shipping_city; ?> <?php echo $shipping_zipcode; ?></div>
                    <div class="small"><?php echo $shipping_state; ?></div>
                    <div class="small"><?php echo $shipping_country; ?></div>
                    <div class="small"><?php echo $shipping_phone; ?></div>
                    <div class="small"><?php echo $shipping_fax; ?></div>
                <?php } ?>


            </div>
        </div>
    </div>

    <div class="sidebar-section mt-4">
        <div class="sidebar-section-header border-bottom mb-3">
            <span class="text-muted">OTHER DETAILS</span>
            <div class="ms-auto">
                <a href="#other_details" class="text-reset" data-bs-toggle="collapse">
                    <i class="ph-caret-down collapsible-indicator"></i>
                </a>
            </div>
        </div>

        <div class="collapse show" id="other_details">
            <div class="sidebar-section-body">

                <div class="row mt-2">
                    <div class="col-lg-6 small ">Customer Type</div>
                    <div class="col-lg-6 small"><?php echo ucwords($customer_type); ?></div>
                </div>

                <div class="row mt-2">
                    <div class="col-lg-6 small text-muted">Default Currency</div>
                    <div class="col-lg-6 small"><?php echo BASE_CURRENCY['code']; ?></div>
                </div>

                <div class="row mt-2">
                    <div class="col-lg-6 small text-muted">Customer Language</div>
                    <div class="col-lg-6 small">English</div>
                </div>

            </div>
        </div>
    </div>


    <?php
    //COUNT QUERY
    $rs                     = $mysqli->query("SELECT id FROM `" . DB::CUSTOMER_CONTACTS . "` WHERE contactable_type='Customer' AND contactable_id=$customer_id ");
    $total_contact_persons  = $rs->num_rows;

    ?>
    <div class="sidebar-section mt-4">
        <div class="sidebar-section-header border-bottom mb-3">
            <span class="text-muted">CONTACT PERSONS (<?php echo $total_contact_persons; ?>)</span>
            <div>
                <a class="btn btn-link" href="customer_contacts.php?customer_id=<?php echo $customer_id; ?>">
                    <i class="ph-plus-circle me-2"></i>
                </a>
            </div>
            <div class="ms-auto">
                <a href="#contact_persons" class="text-reset" data-bs-toggle="collapse">
                    <i class="ph-caret-down collapsible-indicator"></i>
                </a>
            </div>
        </div>

        <div class="collapse show" id="contact_persons">
            <div class="sidebar-section-body">

                <?php
                // ----------------------------------------------------------------
                $result_contacts = $mysqli->query("SELECT * FROM `" . DB::CUSTOMER_CONTACTS . "` WHERE contactable_type='Customer' AND contactable_id=$customer_id AND is_primary=0");
                while ($rows_contacts = $result_contacts->fetch_array()) {
                    // ----------------------------------------------------------------
                ?>
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="me-2 position-relative">
                                <div class="bg-success bg-opacity-10 text-success lh-1 rounded-pill p-2">
                                    <i class="ph-user"></i>
                                </div>
                            </div>

                            <div class="flex-fill">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div><span class="text-muted small">
                                            <?php echo $rows_contacts['first_name']; ?> <?php echo $rows_contacts['last_name']; ?></span><br />
                                        <?php echo $rows_contacts['email']; ?>
                                    </div>
                                    <span class="fs-sm text-muted">
                                        <div class="dropdown d-inline-block">
                                            <button type="button" class="btn btn-sm me-2" data-bs-toggle="dropdown">
                                                <i class="ph-gear"></i>
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item small" href="customer_contacts.php?action=edit_customer_contacts&customer_id=<?php echo $customer_id; ?>&contact_id=<?php echo $rows_contacts['id']; ?>">
                                                    Edit
                                                </a>
                                                <form method="post" action="customer_overview.php" class="d-inline">
                                                    <input type="hidden" name="action" value="mark_as_primary">
                                                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                                                    <input type="hidden" name="contact_id" value="<?php echo $rows_contacts['id']; ?>">
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit" class="dropdown-item small">Mark as Primary</button>
                                                </form>
                                                <a class="dropdown-item small" href="customer_overview.php?customer_id=<?php echo $customer_id; ?>&action=delete_customer_contacts&contact_id=<?php echo $rows_contacts['id']; ?>">
                                                    Delete
                                                </a>
                                            </div>
                                        </div>
                                    </span>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php } ?>




            </div>
        </div>
    </div>



    <div class="sidebar-section mt-4">
        <div class="sidebar-section-header border-bottom mb-3">
            <span class="text-muted">RECORD INFO</span>
            <div class="ms-auto">
                <a href="#record_info" class="text-reset" data-bs-toggle="collapse">
                    <i class="ph-caret-down collapsible-indicator"></i>
                </a>
            </div>
        </div>

        <div class="collapse" id="record_info">
            <div class="sidebar-section-body">

                <div class="row mt-2">
                    <div class="col-lg-6 small text-muted">Customer ID</div>
                    <div class="col-lg-6 small"><?php echo $customer_id; ?></div>
                </div>

                <div class="row mt-2">
                    <div class="col-lg-6 small text-muted">Created On</div>
                    <div class="col-lg-6 small"><?php echo ddm_($created_at); ?></div>
                </div>

                <div class="row mt-2">
                    <div class="col-lg-6 small text-muted">Created By</div>
                    <div class="col-lg-6 small"><?php echo getTableAttr('full_name', DB::USERS, $created_by); ?></div>
                </div>

                <?php if ($approved == 1) { ?>

                    <div class="row mt-2">
                        <div class="col-lg-6 small text-muted">Approved By</div>
                        <div class="col-lg-6 small">Zaki on <?php echo date('d M Y g:ia', strtotime($approved_at)); ?></div>
                    </div>

                <?php } ?>


            </div>
        </div>
    </div>


</div>