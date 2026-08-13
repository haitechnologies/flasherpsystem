<?php

declare(strict_types=1);

use App\Core\Container;
use App\Core\DB;
use App\Core\Session;
use App\Service\CreditNoteService;

include('admin_elements/admin_header.php');

$module = 'credit_notes';
$module_caption = 'Credit Note';
$module_id = getModuleIdBySlug($module, $mysqli);
$tbl_name = DB::CREDIT_NOTES;
$error_message = '';
$success_message = '';
$page = $_GET['page'] ?? '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_credit_notes.php', 'WARNING', __FILE__, __LINE__);
        $action = '';
    }
}

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    if (!validate_csrf_token($_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '')) {
        log_error('CSRF token validation failed on delete in listing_credit_notes.php', 'WARNING', __FILE__, __LINE__);
        flash_error('Invalid security token. Please refresh the page and try again.');
        header("Location:listing_$module.php?page=$page");
        exit;
    }
    try {
        $creditNoteService = Container::getInstance()->get(CreditNoteService::class);
        $creditNote = $creditNoteService->getCreditNote((int)$id, (int)$activeOrganizationId);

        $canDelete = has_full_access() || (int)$creditNote->createdBy === (int)Session::userId();
        if (!$canDelete) {
            flash_error("You do not have permission to delete this credit note");
            log_error("IDOR attempt: User " . Session::userId() . " tried to delete credit note $id", 'WARNING', __FILE__, __LINE__);
        } else {
            $creditNoteService->deleteNote((int)$id, (int)$activeOrganizationId);
            $success_message = "$module_caption Deleted Successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php?page=$page");
            exit;
        }
    } catch (\Throwable $e) {
        log_error('Delete failed for credit note: ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'credit_notes', 'action' => 'delete', 'id' => (int)($id ?? 0)]);
        flash_error($e->getMessage());
    }
}
?>

<style>
.hover-primary:hover {
    color: #0d6efd !important;
}
.fs-7 {
    font-size: 0.85rem !important;
}
.fs-8 {
    font-size: 0.75rem !important;
}
.badge.bg-opacity-10 {
    border: 1px solid rgba(0, 0, 0, 0.05);
}
.dropdown-menu {
    border-radius: 8px;
}
.dropdown-item {
    transition: background-color 0.15s ease;
}
.dropdown-item:hover {
    background-color: #f8f9fa;
}
</style>

<div class="content-wrapper">

    <!-- Standardized Navbar/Header -->
    <div class="page-header page-header-light shadow mb-4 carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <!-- Left Side: Heading & Subtitle -->
            <div class="my-1">
                <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                    <a href="listing_<?php echo $module; ?>.php" class="text-dark">All <?php echo ucwords(str_ireplace('_', " ", $module)); ?></a>
                </h1>
            </div>

            <!-- Right Side: Action Buttons -->
            <div class="my-1 d-flex align-items-center gap-2">
                <?php if (isset($module_id) && granted('create', $module_id)): ?>
                    <a href="credit_notes.php" class="btn btn-primary btn-sm d-inline-flex align-items-center">
                        <i class="ph-plus ph-sm me-2 opacity-75"></i>New
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <div class="content datatable-enhanced px-4">

        <?php include('admin_elements/breadcrumb.php'); ?>

        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-body p-0">
                <!-- CSRF Protection Token -->
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                <div class="table-responsive">
<table id="grid-<?php echo $module; ?>" class="table table-hover align-middle mb-0 custom_datatables datatable-professional display responsive nowrap" width="100%">
                    <thead>
                        <tr>
                            <th width="100">DATE</th>
                            <th width="150">CREDIT NOTE #</th>
                            <th>REFERENCE #</th>
                            <th>CUSTOMER NAME</th>
                            <th width="100" class="col-center">STATUS</th>
                            <th width="100" class="text-end">AMOUNT</th>
                        </tr>
                    </thead>
                </table>
</div>
            </div>
        </div>

    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>

<script>
$(document).ready(function() {

    window.HAIDatatableInitializer.init('#grid-<?php echo $module; ?>', '<?php echo $module; ?>', {
        stateSave: false,
        deferRender: true,
        retrieve: false,
        ajax: {
            url: 'datatables.php',
            type: 'POST',
            data: function(d) {
                d.csrf_token = window.HAI_CSRF_TOKEN || $('input[name="csrf_token"]').first().val() || '';
                d.action = '<?php echo $action; ?>';
                d.edit_permission = <?php echo granted('edit', $module_id) ? '1' : '0'; ?>;
                d.delete_permission = <?php echo granted('delete', $module_id) ? '1' : '0'; ?>;
                d.session_user_id = '<?php echo Session::userId() ?? ''; ?>';
                d.dt_session_role_id = '<?php echo Session::roleId() ?? ''; ?>';
                return d;
            },
            error: function(xhr, status, error) {
                console.error('[Credit Note] DataTable AJAX error:', error);
                console.error('[Credit Note] Response:', xhr.responseText);
            }
        },
        columns: [
            { data: 0 }, // col 0: Date
            { data: 1 }, // col 1: Credit Note #
            { data: 2 }, // col 2: Reference
            { data: 3 }, // col 3: Customer
            { data: 4, className: 'col-center' }, // col 4: Status
            { data: 5, className: 'text-end' } // col 5: Amount
        ],
        columnDefs: [
            {
                targets: 1,
                render: function(data, type, row) {
                    var cnNo = row[1] || '';
                    var id = row[6] || '';
                    return '<a href="credit_note_overview.php?credit_note_id=' + id + '" class="text-primary text-decoration-none hover-primary">' + cnNo + '</a>';
                }
            },
            {
                targets: 3,
                render: function(data, type, row) {
                    var custName = row[3] || '';
                    var id = row[6] || '';
                    return '<a href="credit_note_overview.php?credit_note_id=' + id + '" class="text-dark text-decoration-none hover-primary">' + custName + '</a>';
                }
            },
            {
                targets: 4,
                render: function(data, type, row) {
                    return row[4] || '<span class="badge bg-secondary bg-opacity-10 text-secondary">Draft</span>';
                }
            },
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: "<'dt-header d-flex justify-content-between align-items-center mb-3'<'dt-head-left'f><'dt-head-right'l>>rt<'dt-footer d-flex justify-content-between align-items-center mt-3'<'dt-foot-left'i><'dt-foot-right'p>>"
    });

});
</script>

<?php include('admin_elements/admin_footer.php'); ?>
