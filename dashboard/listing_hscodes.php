<?php

use App\Core\DB;
use App\Core\DeletionManager;
use App\Core\Session;

/**
 * HS Codes Listing Page (Redesigned)
 *
 * Displays a server-side DataTable of HS Codes (Harmonized System) with search, sort, and pagination
 * Uses the new modular datatables_dispatcher.php for AJAX requests
 */

include('admin_elements/admin_header.php');

// Module configuration
$module = 'hscodes';
$module_caption = 'HS Code';
$tbl_name = DB::HS_CODES;
$module_id = getModuleIdBySlug($module, $mysqli);

$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

/*
|--------------------------------------------------------------------------
| HANDLE DELETE ACTION
|--------------------------------------------------------------------------
*/
if ($action == "delete_$module" && !empty($id)) {

    // Check delete permission
    if (!granted('delete', $module)) {
        $error_message = "You don't have permission to delete HS Codes (Harmonized System).";
    } else {
        // Use centralized deletion manager
        $result = DeletionManager::delete(
            $tbl_name,
            $id,
            Session::userId(),
            [
                'verify_field' => 'code',
                'item_label' => 'HS Code',
                'module_slug' => 'hscodes'
            ]
        );

        if ($result['success']) {
            $success_message = $result['message'];
            header("Location: listing_$module.php?msg=deleted");
            exit;
        } else {
            $error_message = $result['message'];
        }
    }
}
?>

<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                    <a href="listing_<?php echo $module; ?>.php" class="text-dark">All <?php echo ucwords(str_ireplace('_', " ", $module)); ?></a>
                </h1>
            </div>

            <div class="my-1">
                <?php if (empty($hide_add_button) && isset($module_id) && isset($module) && granted('create', $module_id)) { ?>
                    <a href="<?php echo $module; ?>.php" class="btn btn-primary btn-sm d-inline-flex align-items-center">
                        <i class="ph-plus ph-sm me-2 opacity-75"></i>New
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content datatable-enhanced">

        <?php include('admin_elements/breadcrumb.php'); ?>

        <!-- Success/Error Messages -->
        <!-- DataTable Card -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="grid-<?php echo $module; ?>" class="custom_datatables datatable-professional display responsive no-wrap table-hover" width="100%">
                        <thead>
                            <tr>
                                <th width="60">ID</th>
                                <th width="120">HS CODE</th>
                                <th width="120">OLD CODE</th>
                                <th>DESCRIPTION (EN)</th>
                                <th>DESCRIPTION (AR)</th>
                                <th width="70">LEVEL</th>
                                <th width="90">VIEWS</th>
                                <th width="100">DUTY %</th>

                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTables will populate this -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>

<!-- HS Code Details Modal -->
<div class="modal fade" id="hscodeDetailsModal" tabindex="-1" aria-labelledby="hscodeDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="hscodeDetailsModalLabel">HS Code Details </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">ID</label>
                        <div id="modal-hs-id" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">HS Code</label>
                        <div id="modal-hs-code" class="fw-semibold">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Old Code</label>
                        <div id="modal-hs-old-code">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Level</label>
                        <div id="modal-hs-level">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Duty Rate</label>
                        <div id="modal-hs-duty">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Views</label>
                        <div id="modal-hs-views">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Status</label>
                        <div id="modal-hs-status">-</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted mb-1">Description (EN)</label>
                        <div id="modal-hs-desc-en" class="border rounded p-2 bg-light">-</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted mb-1">Description (AR)</label>
                        <div id="modal-hs-desc-ar" class="border rounded p-2 bg-light" dir="rtl">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>

<script type="text/javascript">
    $(document).ready(function() {
        console.log('[HSCodes] Page loaded, initializing DataTable...');

        // Debug: Check if table element exists
        var tableElement = $('#grid-hscodes');
        console.log('[HSCodes] Table element found:', tableElement.length > 0);
        console.log('[HSCodes] Table ID:', tableElement.attr('id'));
        console.log('[HSCodes] Table class:', tableElement.attr('class'));

        console.log('[HSCodes] DataTable initialization started...');

        try {
            window.HAIDatatableInitializer.init('#grid-hscodes', 'hscodes', {
                stateSave: false,
                deferRender: true,
                retrieve: false,
                dom: "<'dt-header'<'dt-head-left'fl><'dt-head-right'>>rt<'dt-footer'<'dt-foot-left'i><'dt-foot-right'p>>",
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                pageLength: 10,
                ajax: {
                    data: function(d) {
                        d.module = 'hscodes';
                        d.edit_permission = <?php echo granted('edit', $module) ? '1' : '0'; ?>;
                        d.delete_permission = <?php echo granted('delete', $module) ? '1' : '0'; ?>;
                        d.session_user_id = '<?php echo e(Session::userId()); ?>';
                        d.session_role_id = '<?php echo e($session_role_id); ?>';
                        return d;

                        console.log('[HSCodes AJAX] Sending request with module=' + d.module + ', ajax_action=' + d.ajax_action);
                    },
                    error: function(xhr, status, error) {
                        console.error('[HSCodes AJAX Error] Status:', status, 'Error:', error);
                        console.error('[HSCodes AJAX] Full Response:', xhr.responseText);
                        console.error('[HSCodes AJAX] Status Code:', xhr.status);

                        var errorMsg = 'Error loading HS Codes (Harmonized System): ';
                        if (xhr.status === 0) {
                            errorMsg += 'Connection refused';
                        } else if (xhr.status === 404) {
                            errorMsg += 'datatables.php not found';
                        } else if (xhr.status === 500) {
                            errorMsg += 'Server error (500)';
                        } else {
                            errorMsg += status;
                        }

                        $('#grid-hscodes').closest('.card-body').prepend(
                            '<div class="alert alert-danger alert-dismissible">' + errorMsg + '<button class="btn-close" data-bs-dismiss="alert"></button></div>'
                        );
                    },
                    complete: function(xhr, status) {
                        console.log('[HSCodes AJAX] Request complete. Status:', status);
                    }
                },
                columns: [{
                        data: 0,
                        className: 'text-center'
                    },
                    {
                        data: 1
                    },
                    {
                        data: 2
                    },
                    {
                        data: 3
                    },
                    {
                        data: 4
                    },
                    {
                        data: 5,
                        className: 'text-center'
                    },
                    {
                        data: 6,
                        className: 'text-right'
                    },
                    {
                        data: 7
                    }
                ],
                order: [
                    [0, 'desc']
                ],
                responsive: true,
                language: {
                    processing: '<i class="fa fa-spinner fa-spin"></i> Loading...',
                    emptyTable: 'No HS Codes (Harmonized System) found',
                    zeroRecords: 'No matching HS Codes (Harmonized System) found',
                    info: 'Showing _START_ to _END_ of _TOTAL_ HS Codes (Harmonized System)',
                    infoEmpty: 'No HS Codes (Harmonized System) to display',
                    infoFiltered: '(filtered from _MAX_ HS Codes (Harmonized System) total count)',
                    search: 'Search:',
                    paginate: {
                        first: 'First',
                        last: 'Last',
                        next: 'Next',
                        previous: 'Previous'
                    }
                },
                initComplete: function() {
                    console.log('[HSCodes] DataTable initialization complete!');
                }
            });

            console.log('[HSCodes] DataTable object created successfully');
        } catch (e) {
            console.error('[HSCodes] Exception during DataTable initialization:', e);
            alert('Error initializing DataTable: ' + e.message);
        }

        // Delete Record Handler
        $(document).on('click', '.delete-hscode-btn', function(e) {
            e.preventDefault();

            var hscode_id = $(this).data('id');
            var code = $(this).data('code');

            if (!confirm('Are you sure you want to delete HS Code: ' + code + '?\n\nThis action cannot be undone.')) {
                return;
            }

            window.location.href = 'listing_<?php echo $module; ?>.php?action=delete_<?php echo $module; ?>&id=' + hscode_id;
        });

        // View/Edit Handler
        $(document).on('click', '.edit-hscode-btn', function(e) {
            e.preventDefault();

            var hscode_id = $(this).data('id');
            window.location.href = 'hscodes.php?action=edit_hscodes&id=' + hscode_id;
        });

        // Row click: show details modal with row data
        $(document).on('click', '#grid-hscodes tbody td', function(e) {
            if ($(e.target).closest('a, button, input, select, textarea').length) {
                return;
            }
            var table = $('#grid-hscodes').DataTable();
            var rowData = table.row($(this).closest('tr')).data();
            if (!rowData) return;
            // DataTable row: [id, code, old_code, desc_en, desc_ar, level, views, duty_rate, ...]
            $('#modal-hs-id').text(rowData[0] || '-');
            $('#modal-hs-code').html(rowData[1] || '-');
            $('#modal-hs-old-code').html(rowData[2] || '-');
            // Level: remove 'Level ' prefix if present
            var levelVal = (rowData[5] || '').toString().replace(/Level\s*/, '');
            $('#modal-hs-level').text(levelVal || '-');
            // Duty Rate: remove % if present
            var dutyVal = (rowData[7] || '').toString().replace(/%/, '');
            $('#modal-hs-duty').text(dutyVal !== '' ? dutyVal + '%' : '0%');
            // Views: extract number from badge
            var viewsMatch = (rowData[6] || '').toString().match(/>([\d,]+)</);
            $('#modal-hs-views').text(viewsMatch ? viewsMatch[1] : (rowData[6] || '-'));
            // Status: show Active/Inactive if possible (not in table, so leave blank)
            $('#modal-hs-status').text('');
            // Description EN/AR
            $('#modal-hs-desc-en').html(rowData[3] || 'No description');
            $('#modal-hs-desc-ar').html(rowData[4] || 'لا يوجد وصف');
            var detailsModal = new bootstrap.Modal(document.getElementById('hscodeDetailsModal'));
            detailsModal.show();
        });
    });
</script>
