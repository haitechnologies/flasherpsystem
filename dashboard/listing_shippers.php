<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Core\DB;

if (($_POST['action'] ?? null) == 'get_shipper_details' && !empty($_POST['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit;
    }
    $id = (int)$_POST['id'];
    $stmt = $mysqli->prepare("SELECT s.*, co.country AS country_name FROM `" . DB::SHIPPERS . "` s LEFT JOIN `" . DB::GEO_COUNTRIES . "` co ON s.country = co.id WHERE s.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();

    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
    }
    exit;
}

include('admin_elements/admin_header.php');

$module = 'shippers';
$module_caption = 'Shipper';
$tbl_name = DB::SHIPPERS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$handler_config = [
    'hard_delete' => true,
    'redirect_on_success' => true,
    'cascade_delete' => [
        "UPDATE `" . DB::INVOICES . "` SET shipper = 0 WHERE shipper = :id",
        "UPDATE `" . DB::CREDIT_NOTES . "` SET shipper = 0 WHERE shipper = :id",
        "UPDATE `" . DB::DEBIT_NOTES . "` SET shipper = 0 WHERE shipper = :id",
        "UPDATE `" . DB::QUOTATIONS . "` SET shipper_id = 0 WHERE shipper_id = :id",
        "UPDATE `" . DB::SALE_ORDERS . "` SET shipper_id = 0 WHERE shipper_id = :id",
    ],
];
include('admin_elements/listing_handler.php');

$detailsModalHtml = '
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Shipper Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modal-loading" class="text-center py-3">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                </div>
                <div id="modal-content" class="d-none">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Shipper ID:</strong> <span id="det-id">-</span></div>
                        <div class="col-md-6"><strong>Status:</strong> <span id="det-status">-</span></div>
                        <div class="col-md-12"><strong>Shipper Name:</strong> <span id="det-name" class="fw-semibold text-primary">-</span></div>
                        <div class="col-md-6"><strong>Street Address 1:</strong> <div id="det-addr1" class="border rounded p-2 bg-light">-</div></div>
                        <div class="col-md-6"><strong>Street Address 2:</strong> <div id="det-addr2" class="border rounded p-2 bg-light">-</div></div>
                        <div class="col-md-4"><strong>City:</strong> <span id="det-city">-</span></div>
                        <div class="col-md-4"><strong>Province/State:</strong> <span id="det-province">-</span></div>
                        <div class="col-md-4"><strong>Zip/Postal Code:</strong> <span id="det-zipcode">-</span></div>
                        <div class="col-md-6"><strong>Country:</strong> <span id="det-country">-</span></div>
                        <div class="col-md-6"><strong>Email:</strong> <span id="det-email">-</span></div>
                        <div class="col-md-4"><strong>Telephone:</strong> <span id="det-telephone">-</span></div>
                        <div class="col-md-4"><strong>Mobile:</strong> <span id="det-mobile">-</span></div>
                        <div class="col-md-4"><strong>Fax:</strong> <span id="det-fax">-</span></div>
                        <div class="col-md-12 text-muted small mt-3"><strong>Created At:</strong> <span id="det-created">-</span></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>';

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'hide_add_button' => true,
    'thead' => '
        <th width="40">SR.</th>
        <th>SHIPPER NAME</th>
        <th>STREET ADDRESS1</th>
        <th width="150">CREATED AT</th>
        <th width="90">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'name' => 'id', 'title' => 'SR.'],
        ['data' => 1, 'name' => 'shipper_name', 'title' => 'SHIPPER NAME'],
        ['data' => 2, 'name' => 'address_line1', 'title' => 'STREET ADDRESS1'],
        ['data' => 3, 'name' => 'created_at', 'title' => 'CREATED AT'],
        ['data' => 4, 'title' => 'ACTION', 'orderable' => false, 'searchable' => false],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'extra_js' => "
        var detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));

        $(document).on('click', '.view-shipper-details', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            $('#modal-loading').removeClass('d-none');
            $('#modal-content').addClass('d-none');
            detailsModal.show();

            $.ajax({
                url: 'listing_shippers.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'get_shipper_details',
                    id: id,
                    csrf_token: $('input[name=\"csrf_token\"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $('#det-id').text(data.id);
                        $('#det-name').text(data.shipper_name);
                        $('#det-addr1').text(data.address_line1 || '-');
                        $('#det-addr2').text(data.address_line2 || '-');
                        $('#det-city').text(data.city || '-');
                        $('#det-province').text(data.province || '-');
                        $('#det-zipcode').text(data.zipcode || '-');
                        $('#det-country').text(data.country_name || ('Country #' + data.country));
                        $('#det-email').text(data.email || '-');
                        $('#det-telephone').text(data.telephone || '-');
                        $('#det-mobile').text(data.mobile || '-');
                        $('#det-fax').text(data.fax || '-');
                        $('#det-status').html(data.is_active == 1 ? '<span class=\"badge bg-success\">Active</span>' : '<span class=\"badge bg-danger\">Inactive</span>');
                        $('#det-created').text(data.created_at || '-');

                        $('#modal-loading').addClass('d-none');
                        $('#modal-content').removeClass('d-none');
                    } else {
                        alert(response.message || 'Failed to fetch details.');
                        detailsModal.hide();
                    }
                },
                error: function() {
                    alert('Error fetching details.');
                    detailsModal.hide();
                }
            });
});

    ",
];

include('admin_elements/listing_template.php');

echo $detailsModalHtml;

include('admin_elements/admin_footer.php');
