<?php
use App\Core\DB;

include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Reports - Accounts';
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

// Define category icons
$categoryIcons = [
    1 => 'ph-presentation-chart',  // Business Overview
    2 => 'ph-shopping-cart',       // Sales
    3 => 'ph-calculator',          // Accountant
    4 => 'ph-hand-coins',          // Receivables
    5 => 'ph-receipt',             // Purchases and Expenses
    6 => 'ph-credit-card',         // Payables
    7 => 'ph-bank',                // Payments Received
    8 => 'ph-calendar-check',      // Recurring Invoices
    9 => 'ph-wallet'               // Banking
];

// Define category gradients
$categoryGradients = [
    1 => 'linear-gradient(90deg, #4facfe 0%, #00f2fe 100%)',
    2 => 'linear-gradient(90deg, #ff0844 0%, #ffb199 100%)',
    3 => 'linear-gradient(90deg, #f12711 0%, #f5af19 100%)',
    4 => 'linear-gradient(90deg, #11998e 0%, #38ef7d 100%)',
    5 => 'linear-gradient(90deg, #fc00ff 0%, #00dbde 100%)',
    6 => 'linear-gradient(90deg, #7f00ff 0%, #e100ff 100%)',
    7 => 'linear-gradient(90deg, #00c6ff 0%, #0072ff 100%)',
    8 => 'linear-gradient(90deg, #e0c3fc 0%, #8ec5fc 100%)',
    9 => 'linear-gradient(90deg, #f093fb 0%, #f5576c 100%)'
];

// Fetch categories and subcategories
$categories = [];
$categoriesQuery = $mysqli->query("SELECT * FROM `" . DB::ACCOUNTS_REPORT_CATEGORIES . "` WHERE is_active = 1 ORDER BY id ASC");
if ($categoriesQuery) {
    while ($cat = $categoriesQuery->fetch_assoc()) {
        $subQuery = $mysqli->query("SELECT * FROM `" . DB::ACCOUNTS_REPORT_SUBCATEGORIES . "` WHERE category_id = " . intval($cat['id']) . " AND is_active = 1 ORDER BY ordering ASC");
        $subcats = [];
        if ($subQuery) {
            while ($sub = $subQuery->fetch_assoc()) {
                $subcats[] = $sub;
            }
        }
        $cat['subcategories'] = $subcats;
        $categories[] = $cat;
    }
}

// Distribute into 3 columns
$cols = [[], [], []];
$i = 0;
foreach ($categories as $cat) {
    if (!empty($cat['subcategories'])) {
        $cols[$i % 3][] = $cat;
        $i++;
    }
}
?>

<style>
/* Premium Dashboard Reports Hub Styles */
.report-card {
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border: none !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05) !important;
    overflow: hidden;
    position: relative;
    background: #ffffff;
    margin-bottom: 24px;
}
.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 28px rgba(103, 126, 234, 0.15) !important;
}
.border-top-gradient::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: var(--gradient-border);
}
.icon-container {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(103, 126, 234, 0.08);
    color: #667eea;
    font-size: 20px;
    transition: all 0.2s ease;
}
.report-card:hover .icon-container {
    background: var(--gradient-border);
    color: #ffffff;
}
.report-list-item {
    transition: all 0.2s ease;
    border-radius: 8px;
    margin-bottom: 6px;
}
.report-list-item:hover {
    background: rgba(103, 126, 234, 0.04) !important;
    transform: translateX(4px);
}
.report-list-item:hover .report-title {
    color: #667eea !important;
    font-weight: 500;
}
.report-list-item:hover i {
    color: #667eea !important;
}
.report-title {
    font-size: 13.5px;
    color: #334155;
    font-weight: 400;
}
.coming-soon-item {
    opacity: 0.6;
    background: transparent;
    cursor: not-allowed;
    border-radius: 8px;
    margin-bottom: 6px;
}
.coming-soon-title {
    font-size: 13.5px;
    color: #64748b;
}
.digital-clock {
    margin: auto;
    padding: 0 10px;
    color: #ffffff;
    background: linear-gradient(90deg, #000, #555);
}
</style>

<!-- Main content -->
<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h5 class="mb-0">Reports Hub</h5>
            </div>

            <div class="my-1 d-flex align-items-center gap-2">
                <span class="text-body"><i class="ph-clock"></i> &nbsp;<?php echo date('j F Y'); ?> &nbsp;<div class="digital-clock d-inline-block">00:00:00</div></span>
                <script>
                    $(document).ready(function() {
                        clockUpdate();
                        setInterval(clockUpdate, 1000);
                    })

                    function clockUpdate() {
                        var date = new Date();
                        $('.digital-clock').css({
                            'color': '#fff',
                            'text-shadow': '0 0 6px #ff0'
                        });

                        function addZero(x) {
                            if (x < 10) {
                                return x = '0' + x;
                            } else {
                                return x;
                            }
                        }

                        function twelveHour(x) {
                            if (x > 12) {
                                return x = x - 12;
                            } else if (x == 0) {
                                return x = 12;
                            } else {
                                return x;
                            }
                        }

                        var h = addZero(twelveHour(date.getHours()));
                        var m = addZero(date.getMinutes());
                        var s = addZero(date.getSeconds());

                        $('.digital-clock').text(h + ':' + m + ':' + s)
                    }
                </script>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Content area -->
        <div class="content">

            <!-- Dashboard content -->
            <div class="row">
                <?php for ($colIndex = 0; $colIndex < 3; $colIndex++) { ?>
                    <div class="col-xl-4 col-md-6 col-sm-12">
                        <?php foreach ($cols[$colIndex] as $cat) {
                            $catId = intval($cat['id']);
                            $iconClass = $categoryIcons[$catId] ?? 'ph-chart-bar';
                            $gradient = $categoryGradients[$catId] ?? 'linear-gradient(90deg, #667eea 0%, #764ba2 100%)';
                            
                            // Count active reports
                            $activeCount = 0;
                            foreach ($cat['subcategories'] as $sub) {
                                if (file_exists(__DIR__ . "/report_{$sub['slug']}.php")) {
                                    $activeCount++;
                                }
                            }
                            ?>
                            <div class="card report-card border-top-gradient" style="--gradient-border: <?= $gradient ?>;">
                                <div class="card-header d-flex align-items-center border-0 pb-0">
                                    <div class="icon-container me-3" style="--gradient-border: <?= $gradient ?>;">
                                        <i class="<?= $iconClass ?>"></i>
                                    </div>
                                    <h5 class="mb-0 font-weight-semibold" style="color: #1e293b; font-size: 15px; letter-spacing: -0.2px;">
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </h5>
                                    <div class="ms-auto">
                                        <span class="badge rounded-pill bg-light text-primary border border-primary-100 px-2 py-1" style="font-size: 10px; font-weight: 600;">
                                            <?= $activeCount ?> / <?= count($cat['subcategories']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body pt-3">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($cat['subcategories'] as $sub) {
                                            $filePath = __DIR__ . "/report_{$sub['slug']}.php";
                                            $exists = file_exists($filePath);
                                            if ($exists) {
                                                ?>
                                                <a href="report_<?= htmlspecialchars($sub['slug']) ?>.php" class="list-group-item list-group-item-action report-list-item d-flex align-items-center py-2 px-3 border-0">
                                                    <i class="ph-file-text me-2 text-muted" style="font-size: 16px;"></i>
                                                    <span class="report-title"><?= htmlspecialchars($sub['report_name']) ?></span>
                                                    <?php if (!empty($sub['last_visited']) && $sub['last_visited'] !== '0000-00-00 00:00:00') { ?>
                                                        <span class="badge bg-light text-muted ms-auto" style="font-size: 9px; font-weight: 500;" title="Last Visited">
                                                            <?= timeAgo($sub['last_visited']) ?>
                                                        </span>
                                                    <?php } ?>
                                                </a>
                                            <?php } else { ?>
                                                <div class="list-group-item coming-soon-item d-flex align-items-center py-2 px-3 border-0">
                                                    <i class="ph-lock me-2 text-muted-300" style="font-size: 16px;"></i>
                                                    <span class="coming-soon-title"><?= htmlspecialchars($sub['report_name']) ?></span>
                                                    <span class="badge bg-light text-muted ms-auto" style="font-size: 8px; font-weight: 500; letter-spacing: 0.3px; text-uppercase;">
                                                        Coming Soon
                                                    </span>
                                                </div>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
            <!-- /dashboard content -->

        </div>
        <!-- /content area -->


        <?php include('admin_elements/copyright.php'); ?>


    </div>
    <!-- /inner content -->

</div>
<!-- /main content -->

</div>
<!-- /page content -->
<?php include('admin_elements/admin_footer.php'); ?>