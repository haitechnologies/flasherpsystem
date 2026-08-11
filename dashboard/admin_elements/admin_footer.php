<?php declare(strict_types=1); 
use App\Core\DB;
use App\Security\Roles;
?>
  

<script type="text/javascript">
  function updateInvoiceModal(invoice_id, invoice_status) {
    // Set the item ID in the modal body
    // $("#invoiceModal .modal-body").text("Are you sure you want to delete? This operation cannot be undone.");

    $("#invoice_id").val(invoice_id);
    $("input[name=invoice_status][value='" + invoice_status + "']").prop("checked", true);

    // invoice Log Commentss - Enable / Disable TextArea on base of Characters
    $('#invoice_log_comments').keyup(function() {
      var lengthA = $('#invoice_log_comments').val().length;
      $('#updateInvoice').prop('enabled', lengthA > 10);
      $('#updateInvoice').prop('disabled', lengthA < 20);
    });

    // $("#invoiceModal .modal-body").text("Are you sure you want to delete? This operation cannot be undone.");

    // Set the delete button's click event handler
    $("#updateInvoice").off("click").on("click", function() {
      // Perform the deletion logic here
      // console.log("Deleting item ID: " + target_page);
      // window.location.href = target_page;

      // Close the modal
      var invoiceModal = bootstrap.Modal.getInstance(document.getElementById("invoiceModal"));
      invoiceModal.hide();
    });
  }
  // https: //fontawesomeicons.com/fa/bootstrap-confirm-delete-modal-popup
</script>






 

<!--
|
----------------------------------------------------------------------------
| CREATE BOOKING BUTTON PROMPT
|--------------------------------------------------------------------------
|
-->
<div class="modal fade" id="createBookingModal" tabindex="-1" role="dialog" aria-labelledby="createBookingModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createBookingModalLabel">Create Booking</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">

        <h6><i class="ph-arrow-right"></i> Make New Booking From Manual Entry.</h6>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmCreateBooking">Create</button>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
  function confirmCreateBookingModal(target_page) {
    // Set the delete button's click event handler
    $("#confirmCreateBooking").off("click").on("click", function() {
      window.location.href = target_page;

    });
  }
</script>




<!--
|
----------------------------------------------------------------------------
| SEND EMAIL MODAL
|--------------------------------------------------------------------------
|
-->
<div class="modal fade" id="sendEmailModal" tabindex="-1" role="dialog" aria-labelledby="sendEmailModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="sendEmailModalLabel">Confirm Send Email</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to send Email this Booking?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmSendEmail">Send Email</button>
      </div>
    </div>
  </div>
</div>


<script type="text/javascript">
  function confirmSendEmailModal(target_page) {
    $("#sendEmailModal .modal-body").text("Are you sure you want to Send Email?");

    // Set the delete button's click event handler
    $("#confirmSendEmail").off("click").on("click", function() {
      window.location.href = target_page;

      // Close the modal
      var sendEmailModal = bootstrap.Modal.getInstance(document.getElementById("sendEmailModal"));
      sendEmailModal.hide();
    });
  }
</script>





<!--
|
----------------------------------------------------------------------------
| DELETE BUTTON PROMPT
|--------------------------------------------------------------------------
|
-->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this item?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
      </div>
    </div>
  </div>
</div>


<!-- Toast Message -->
<!-- <div class="toast align-items-center position-fixed bottom-0 end-0" role="alert" aria-live="assertive" aria-atomic="true" style="width: 300px;">
  <div class="toast-header">
    <strong class="me-auto">Bootstrap</strong>
    <small class="text-muted" id="toastTime"></small>
    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
  </div>
  <div class="toast-body">
    Hello, world! This is a toast message.
  </div>
</div> -->


<script type="text/javascript">
  // function getCurrentTime() {
  //   const now = new Date();
  //   const timeString = now.toLocaleTimeString([], {
  //     hour: '2-digit',
  //     minute: '2-digit'
  //   });
  //   return timeString;
  // }

  function confirmDeleteModal(target_page) {
    // Set the item ID in the modal body
    // $("#deleteModal .modal-body").text("Are you sure you want to delete item ID: " + itemId + "?");
    $("#deleteModal .modal-body").text("Are you sure you want to delete? This operation cannot be undone.");

    // Set the delete button's click event handler
    $("#confirmDelete").off("click").on("click", function() {
      // Perform the deletion logic here
      // console.log("Deleting item ID: " + target_page);
      window.location.href = target_page;

      // Close the modal
      var deleteModal = bootstrap.Modal.getInstance(document.getElementById("deleteModal"));
      deleteModal.hide();

      // Show the toast message
      // var toast = $(".toast");
      // toast.find('.toast-body').text('Item 10 Deleted Successfully');
      // $("#toastTime").text(getCurrentTime());
      // toast.toast("show");
    });
  }
  // https: //fontawesomeicons.com/fa/bootstrap-confirm-delete-modal-popup
</script>














<?php if (preg_match('/view_booking.php/', $page_url)) { ?>
  <!--
|
----------------------------------------------------------------------------
| ASSIGN DRIVER TO BOOKING
|--------------------------------------------------------------------------
|
-->
  <div class="modal fade" id="assignDriverModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content w-100" style="width: 1000px !important;">

        <div class="modal-header">
          <input type="hidden" name="booking_item_id" id="booking_item_id">
          <h5 class="modal-title">Assign New Driver | Requested Date: <span id="_requested_date_time"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">

          <!-- Itinerary Details -->
          <h6 class="modal-title">
            Itinerary Details: <span id="booking_item_itn"></span> <br />
            Requested Vehicle: <span id="booking_vehicle_type"></span>
          </h6>


          <div class="wrapper">
            <div id="dmodal" class="carousel">

              <!-- <div><img src="https://picsum.photos/300/200?random=2" alt="200?random=2"> <br />Assign</div>
                  <div><img src="https://picsum.photos/300/200?random=3" alt="200?random=3"> <br />Assign</div>
                  <div><img src="https://picsum.photos/300/200?random=4" alt="200?random=4"> <br />Assign</div>
                  <div><img src="https://picsum.photos/300/200?random=5" alt="200?random=5"> <br />Assign</div>
                  <div><img src="https://picsum.photos/300/200?random=6" alt="200?random=6"> <br />Assign</div> -->

            </div>
          </div>


        </div>
        <!-- modal-body -->

      </div>
    </div>
  </div>

  <script type="text/javascript">
    // $(".modal").focusout(function() {
    // alert('i m in');
    // $('.carousel').empty();
    // });

    $('#assignDriverModal').on("hide.bs.modal", function() {
      // alert("clesn up!")
      // $('#dmodal').empty();
    })


    function confirmDriverModal(booking_item_id, requested_date_time, booking_item_itn, booking_vehicle_type) {

      // $("#booking_item_id").value(booking_item_id);
      document.getElementById("booking_item_id").value = booking_item_id;

      $("#_requested_date_time").text(requested_date_time);
      $("#booking_item_itn").text(booking_item_itn);
      $("#booking_vehicle_type").text(booking_vehicle_type);

      // Set the delete button's click event handler
      $("#confirmAssignDriver").off("click").on("click", function() {

        // var vehicle_id = $("#vehicle_id").val();

        // if (vehicle_id > 0) {
        //   window.location.href = 'listing_drivers.php?action=assign_vehicle&vehicle_id=' + vehicle_id + '&driver_id=' + driver_id;
        // }

        // ajax_load_drivers(document.getElementById("booking_item_id").value);

      });
    }

    // ajax_load_drivers(document.getElementById("booking_item_id").value);
  </script>
<?php } ?>




<!-- ---------------------- BLOG CATEGORIES ARABIC TRANSLATION BUTTON PROMPT ---------------------- -->
<!-- <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal" data-bs-whatever="@mdo">Open modal for @mdo</button> -->

<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Update the Translation - Arabic</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="post" id="frmTranslation" name="frmTranslation">
        <input type="hidden" name="action" id="action" value="update_translation" />
        <input type="hidden" name="id" id="id" />

        <div class="modal-body">
          <div class="mb-3">
            <label class="col-form-label fw-semibold">English:</label>
            <input type="text" class="form-control" name="english_translation" id="english_translation" readonly>
          </div>
          <div class="mb-3">
            <label class="col-form-label fw-semibold">Arabic:</label>
            <textarea dir="rtl" autofocus class="form-control" name="arabic_translation" id="arabic_translation"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success">Save</button>
        </div>
      </form>

    </div>
  </div>
</div>

<script type="text/javascript">
  var exampleModal = document.getElementById('exampleModal')
  exampleModal.addEventListener('show.bs.modal', function(event) {
    // Button that triggered the modal
    var button = event.relatedTarget
    // Extract info from data-bs-* attributes
    var recipient = button.getAttribute('data-bs-whatever')

    // alert(recipient);

    var data_array = recipient.split('#####');
    // If necessary, you could initiate an AJAX request here
    // and then do the updating in a callback.
    //
    // Update the modal's content.
    // var modalTitle = exampleModal.querySelector('.modal-title')
    // var modalBodyInput = exampleModal.querySelector('.modal-body input')
    // modalTitle.textContent = 'New message to ' + recipient
    // modalBodyInput.value = recipient

    var frmTranslation = exampleModal.querySelector('#id')
    frmTranslation.value = data_array[0];
    // alert(data_array[0]);

    var english_translation = exampleModal.querySelector('#english_translation')
    english_translation.value = data_array[1];

    var arabic_translation = exampleModal.querySelector('#arabic_translation')
    arabic_translation.value = data_array[2];
  })
</script>

<!-- ---------------------- END BLOG CATEGORIES ARABIC TRANSLATION BUTTON PROMPT ---------------------- -->



<?php
if (!function_exists('adminFooterTableExists')) {
  function adminFooterTableExists($mysqli, $tableName)
  {
    if (!$mysqli instanceof mysqli || trim((string)$tableName) === '') {
      return false;
    }

    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      return false;
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
  }
}

$footerOrganizations = function_exists('dashboardGetAccessibleOrganizations') ? (array)dashboardGetAccessibleOrganizations() : [];
$footerActiveOrganizationId = function_exists('dashboardGetActiveOrganizationId') ? (int)dashboardGetActiveOrganizationId(false) : 0;
$footerCanManageOrganizations = false;
if (function_exists('has_full_access') && has_full_access()) {
  $footerCanManageOrganizations = true;
} elseif (function_exists('dashboardUserIsOrganizationOwner')) {
  $footerCanManageOrganizations = dashboardUserIsOrganizationOwner($footerActiveOrganizationId, (int)($session_user_id ?? 0));
}

$footerAlertRows = [];
if (adminFooterTableExists($mysqli, DB::BACKEND_ERROR_LOGS)) {
  $resultAlerts = $mysqli->query("SELECT severity, message, created_at, module_slug, page_name FROM `" . DB::BACKEND_ERROR_LOGS . "` WHERE severity NOT IN ('INFO','DEBUG') ORDER BY id DESC LIMIT 8");
  while ($resultAlerts instanceof mysqli_result && ($row = $resultAlerts->fetch_assoc())) {
    $footerAlertRows[] = $row;
  }
  if ($resultAlerts instanceof mysqli_result) {
    $resultAlerts->free();
  }
}

$footerProfileName = trim((string)($session_full_name ?? 'User'));
$footerProfilePhoto = $base_url . '/images/no-image-profile-photo.png';
if (!empty($session_user_id) && function_exists('getTableAttr')) {
  $footerPhotoFile = (string)getTableAttr('photo', DB::USERS, $session_user_id);
  if ($footerPhotoFile !== '' && file_exists('../uploads/users/thumbs/' . $footerPhotoFile)) {
    $footerProfilePhoto = $base_url . '/uploads/users/thumbs/' . $footerPhotoFile;
  }
}

$footerSystemModuleMap = [
  'crm' => ['leads', 'projects', 'jobs', 'job_statuses'],
  'shipping' => ['shipping_advices', 'shipping_invoices', 'shipping_stocks', 'shipping_customers', 'hscodes', 'ports', 'carriers', 'consignees', 'shippers'],
  'hr' => ['departments', 'designations', 'attendance', 'leave_requests', 'leave_types', 'payroll_components', 'salary_structures', 'employee_salaries', 'payroll_runs', 'payslips', 'user_documents', 'users', 'report_hr'],
  'accounting' => ['banks', 'customers', 'quotations', 'sale_orders', 'invoices', 'payments_received', 'credit_notes', 'vendors', 'expenses', 'purchase_orders', 'purchases', 'payments_made', 'debit_notes', 'journals', 'accounts'],
];

$footerHasSystemAccess = function ($modules) {
  if (function_exists('has_full_access') && has_full_access()) return true;
  foreach ($modules as $module) {
    if (function_exists('granted_') && granted_('view', $module)) return true;
  }
  return false;
};

$footerAvailableSystems = [];
$footerSystemCandidates = [
  'crm' => [
    'label' => 'CRM',
    'icon' => 'ph-users-three',
    'href' => 'listing_leads.php',
    'desc' => 'Leads, customers, projects and jobs',
  ],
  'shipping' => [
    'label' => 'Shipping',
    'icon' => 'ph-package',
    'href' => 'listing_shipping_advices.php',
    'desc' => 'Advice, invoices, stocks and master data',
  ],
  'hr' => [
    'label' => 'HR',
    'icon' => 'ph-identification-card',
    'href' => 'listing_user_documents.php',
    'desc' => 'Attendance, leave, payroll and documents',
  ],
  'accounting' => [
    'label' => 'Accounting',
    'icon' => 'ph-currency-circle-dollar',
    'href' => 'listing_invoices.php',
    'desc' => 'Invoices, expenses, payments and reports',
  ],
];

foreach ($footerSystemCandidates as $systemKey => $systemMeta) {
  // Operations role (4) — only show CRM and Shipping in systems panel
  if ((int)(\App\Core\Session::roleId() ?? 0) === 4 && !in_array($systemKey, ['crm', 'shipping'], true)) {
    continue;
  }
  $enabled = function_exists('dashboardIsSystemEnabled') ? dashboardIsSystemEnabled($systemKey) : true;
  $hasAccess = function_exists('dashboardHasSystemAccess') ? dashboardHasSystemAccess($systemKey) : true;
  $hasModulePerm = isset($footerSystemModuleMap[$systemKey]) ? $footerHasSystemAccess($footerSystemModuleMap[$systemKey]) : true;
  if ($enabled && $hasAccess && $hasModulePerm) {
    $footerAvailableSystems[] = $systemMeta;
  }
}
?>

<!-- Alerts -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="notifications" aria-labelledby="notificationsLabel">
  <div class="offcanvas-header py-0">
    <h5 class="offcanvas-title py-3" id="notificationsLabel">Alerts</h5>
    <button type="button" class="btn btn-light btn-sm btn-icon border-transparent rounded-pill" data-bs-dismiss="offcanvas">
      <i class="ph-x"></i>
    </button>
  </div>

  <div class="offcanvas-body p-0">
    <div class="bg-danger text-white fw-medium py-2 px-3">Latest High Priority</div>
    <div class="p-3">
      <?php if (empty($footerAlertRows)): ?>
        <div class="text-muted small">No active alerts right now.</div>
      <?php else: ?>
        <?php foreach ($footerAlertRows as $alertRow): ?>
          <?php
            $alertSeverity = strtoupper((string)($alertRow['severity'] ?? 'ERROR'));
            $badgeClass = 'bg-danger';
            if ($alertSeverity === 'WARNING') {
              $badgeClass = 'bg-warning text-dark';
            } elseif ($alertSeverity === 'NOTICE') {
              $badgeClass = 'bg-info text-dark';
            }
            $alertMeta = trim((string)($alertRow['module_slug'] ?? ''));
            if ($alertMeta === '') {
              $alertMeta = trim((string)($alertRow['page_name'] ?? ''));
            }
          ?>
          <div class="d-flex align-items-start mb-3">
            <div class="me-2 mt-1">
              <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($alertSeverity, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="flex-fill">
              <div class="small fw-semibold"><?php echo htmlspecialchars((string)($alertRow['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="fs-sm text-muted mt-1">
                <?php if ($alertMeta !== ''): ?>
                  <?php echo htmlspecialchars($alertMeta, ENT_QUOTES, 'UTF-8'); ?> |
                <?php endif; ?>
                <?php echo htmlspecialchars((string)($alertRow['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="d-flex align-items-start pt-2 border-top">
        <div class="flex-fill">
          <a href="view_backend_error_logs.php">Open Logs Center</a>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- /alerts -->

<?php if (Roles::currentUserHasFullAccess()): ?>
<!-- Organizations -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="organizations" aria-labelledby="organizationsLabel">
  <div class="d-flex align-items-center p-3 border-bottom">
    <h6 class="mb-0" id="organizationsLabel">Organizations</h6>
    <div class="ms-auto">
      <a href="listing_organizations.php">Manage</a>
    </div>
  </div>

  <div class="offcanvas-body p-0">
    <div class="bg-light fw-medium py-2 px-3">My Organizations</div>

    <div class="p-3">
      <?php if (empty($footerOrganizations)): ?>
        <div class="text-muted small">No organizations are available for your account.</div>
      <?php else: ?>
        <?php foreach ($footerOrganizations as $organization): ?>
          <?php
            $organizationId = (int)($organization['id'] ?? 0);
            $organizationName = trim((string)($organization['warehouse_name'] ?? 'Organization'));
            $isActiveOrganization = $organizationId > 0 && $organizationId === $footerActiveOrganizationId;
          ?>
          <div class="d-flex align-items-start mb-3">
            <div class="me-3">
              <div class="bg-primary bg-opacity-10 text-primary rounded-pill">
                <i class="ph-buildings p-2"></i>
              </div>
            </div>

            <div class="flex-fill">
              <a href="select_organization.php?organization_id=<?php echo $organizationId; ?>" class="text-decoration-none fw-semibold">
                <?php echo htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($isActiveOrganization): ?>
                  <span class="badge bg-success ms-1">Active</span>
                <?php endif; ?>
              </a>

              <div class="fs-sm text-muted mt-1">Organization ID: <?php echo $organizationId; ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- /organizations -->
<?php endif; ?>


<!-- Systems Panel -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="systemsPanel" aria-labelledby="systemsPanelLabel">
  <div class="offcanvas-header py-2 border-bottom">
    <h5 class="offcanvas-title" id="systemsPanelLabel">Available Systems</h5>
    <button type="button" class="btn btn-light btn-sm btn-icon border-transparent rounded-pill" data-bs-dismiss="offcanvas">
      <i class="ph-x"></i>
    </button>
  </div>

  <div class="offcanvas-body p-0">
    <div class="p-3 border-bottom">
      <div class="text-muted text-uppercase fs-sm fw-semibold">Systems</div>
      <small class="text-muted">Modules available for your account and current organization.</small>
    </div>

    <div class="p-3">
      <?php if (empty($footerAvailableSystems)): ?>
        <div class="text-muted small">No systems are currently available for your account.</div>
      <?php else: ?>
        <div class="d-grid gap-2">
          <?php foreach ($footerAvailableSystems as $sys): ?>
            <a href="<?php echo htmlspecialchars((string)$sys['href'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-light text-start w-100 d-flex flex-column align-items-start">
              <div class="d-flex align-items-center mb-1">
                <i class="<?php echo htmlspecialchars((string)$sys['icon'], ENT_QUOTES, 'UTF-8'); ?> me-2"></i>
                <span class="fw-semibold"><?php echo htmlspecialchars((string)$sys['label'], ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="small text-muted ms-4"><?php echo htmlspecialchars((string)$sys['desc'], ENT_QUOTES, 'UTF-8'); ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- /systems panel -->


</main>
<!-- /main content -->

<style>
.select2-container--default .select2-selection--single {
    height: auto;
    min-height: 38px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 38px;
    font-family: 'Noto Sans', 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
    font-size: 0.8125rem;
}
.select2-container--default .select2-selection--multiple {
    border-color: #D1D5DB;
    min-height: 38px;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #E5E7EB;
    border-color: #E5E7EB;
    color: #1F2937;
    border-radius: calc(0.375rem - 1px);
    padding: 0.125rem 1rem;
    font-size: 0.8125rem;
    line-height: 1.5;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    float: right;
    margin-left: 0.5rem;
    font-size: 1.25rem;
    opacity: 0.75;
    border: none;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice:hover {
    background-color: #0c83ff;
    border-color: #0c83ff;
    color: #fff;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice:hover .select2-selection__choice__remove {
    opacity: 1;
    color: #fff;
}
.select2-container--default .select2-selection--multiple .select2-selection__rendered {
    padding: 2px 6px;
}
.select2-container--default .select2-search--inline .select2-search__field {
    font-family: inherit;
    font-size: 0.8125rem;
    margin-top: 0;
}
.select2-container--default .select2-results__option--highlighted {
    background-color: #F3F4F6;
    color: #1F2937;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #E5E7EB;
    color: #1F2937;
}
.select2-container--default.select2-container--open .select2-selection--multiple,
.select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: #0c83ff;
}
</style>
<script>
$(function() {
    $('select[data-tags="true"], select.select2-enable').select2({
        width: '100%'
    });
});
</script>

</body>

</html>

<?php
$mysqli->close();
ob_flush();
