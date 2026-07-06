<?php

declare(strict_types=1);

/**
 * Listing Page Template
 *
 * Renders the standard listing page layout with DataTable.
 * Each listing page defines a $listingConfig array before including this file.
 *
 * Required keys in $listingConfig:
 *   'module'        => string  (e.g., 'banks', 'customers')
 *   'columns'       => array   (DataTable column definitions)
 *   'thead'         => string  (HTML for <thead> contents)
 *
 * Optional keys:
 *   'module_caption' => string (display name, defaults to ucwords(module))
 *   'page_length'    => int    (default: 25)
 *   'order'          => array  (default: [[2, 'asc']])
 *   'search_placeholder' => string
 *   'searching'      => bool   (default: true)
 *   'state_save'     => bool   (default: false)
 *   'extra_js'       => string (additional JS code to append)
 *   'hide_add_button'=> bool   (default: false)
 *   'extra_header'   => string (HTML to add after the page header)
 *   'before_table'   => string (HTML to add before the table)
 *   'table_classes'  => string (additional table classes)
 */

if (!isset($listingConfig) || !is_array($listingConfig)) {
    return;
}

$_ltModule     = $listingConfig['module'] ?? '';
$_ltCaption    = $listingConfig['module_caption'] ?? ucwords(str_replace('_', ' ', $_ltModule));
$_ltColumns    = $listingConfig['columns'] ?? [];
$_ltThead      = $listingConfig['thead'] ?? '';
$_ltPageLen    = $listingConfig['page_length'] ?? 25;
$_ltOrder      = $listingConfig['order'] ?? [[2, 'asc']];
$_ltSearchPh   = $listingConfig['search_placeholder'] ?? 'Search ' . strtolower($_ltCaption) . '...';
$_ltSearching  = $listingConfig['searching'] ?? true;
$_ltStateSave  = $listingConfig['state_save'] ?? false;
$_ltExtraJs    = $listingConfig['extra_js'] ?? '';
$_ltHideAdd    = $listingConfig['hide_add_button'] ?? false;
$_ltExtraHdr   = $listingConfig['extra_header'] ?? '';
$_ltBeforeTbl  = $listingConfig['before_table'] ?? '';
$_ltAfterCard  = $listingConfig['after_card'] ?? '';
$_ltTableCls   = $listingConfig['table_classes'] ?? 'custom_datatables datatable-professional display responsive no-wrap table-hover';
$_ltDtOptions  = $listingConfig['dt_options'] ?? [];
$_ltCustomInit = $listingConfig['custom_dt_init'] ?? false;
$_ltGridId     = 'grid-' . $_ltModule;
?>

<div class="content-wrapper">

    <?php if (!empty($_ltExtraHdr)): ?>
        <?php echo $_ltExtraHdr; ?>
    <?php endif; ?>

    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                    <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                        <a href="listing_<?php echo htmlspecialchars($_ltModule); ?>.php" class="text-dark">All <?php echo htmlspecialchars($_ltCaption); ?></a>
                    </h1>
            </div>

            <div class="my-1">
                <?php if (!$_ltHideAdd && isset($module_id) && isset($_ltModule) && granted('create', $module_id)): ?>
                    <a href="<?php echo htmlspecialchars($_ltModule); ?>.php" class="btn btn-primary btn-sm d-inline-flex align-items-center">
                        <i class="ph-plus ph-sm me-2 opacity-75"></i>New
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content datatable-enhanced">

        <?php include(__DIR__ . '/breadcrumb.php'); ?>

        <?php if (!empty($_ltBeforeTbl)): ?>
            <?php echo $_ltBeforeTbl; ?>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div class="table-responsive">
                    <table id="<?php echo $_ltGridId; ?>" class="<?php echo htmlspecialchars($_ltTableCls); ?>" width="100%">
                        <thead>
                            <tr>
                                <?php echo $_ltThead; ?>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        <?php if (!empty($_ltAfterCard)): ?>
            <?php echo $_ltAfterCard; ?>
        <?php endif; ?>

    </div>

    <?php include(__DIR__ . '/copyright.php'); ?>
</div>

<script>
    $(document).ready(function() {
        var tableSelector = '#<?php echo $_ltGridId; ?>';

        <?php if ($_ltCustomInit): ?>
        <?php echo $_ltExtraJs; ?>
        <?php else: ?>
        var dtBaseOptions = {
            columns: <?php echo json_encode($_ltColumns); ?>,
            order: <?php echo json_encode($_ltOrder); ?>,
            pageLength: <?php echo (int)$_ltPageLen; ?>,
            dom: "<'dt-header'<'dt-head-left'fl><'dt-head-right'>>rt<'dt-footer'<'dt-foot-left'i><'dt-foot-right'p>>",
            language: {
                search: '',
                searchPlaceholder: '<?php echo addslashes($_ltSearchPh); ?>',
                lengthMenu: '_MENU_'
            },
            searching: <?php echo $_ltSearching ? 'true' : 'false'; ?>,
            stateSave: <?php echo $_ltStateSave ? 'true' : 'false'; ?>,
            deferRender: true
        };
        var dtExtraOptions = <?php echo json_encode($_ltDtOptions); ?>;
        window.HAIDatatableInitializer.init(tableSelector, '<?php echo addslashes($_ltModule); ?>', $.extend({}, dtBaseOptions, dtExtraOptions));

        <?php echo $_ltExtraJs; ?>
        <?php endif; ?>
    });
</script>
