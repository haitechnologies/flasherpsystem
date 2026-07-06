<?php

declare(strict_types=1);

include('admin_elements/admin_header.php');

$module = 'currencies';
$module_caption = 'Currency';

$currencies = [
    ['code' => 'AED', 'name' => 'AED - UAE Dirham', 'primary' => true, 'active' => true],
    ['code' => 'USD', 'name' => 'USD - US Dollar',  'primary' => false, 'active' => true],
];
?>

<div class="content-wrapper">
    <div class="page-header page-header-light shadow">
        <div class="page-header-content border-top py-2 px-3">
            <div class="my-1 d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><?php echo $module_caption; ?>s</h5>
            </div>
        </div>
    </div>
    <div class="content-inner">
        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th width="40">SR.</th>
                                <th>CURRENCY</th>
                                <th width="120">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($currencies as $c) { ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                        <?php if ($c['primary']) { ?>
                                            <span class="badge bg-primary ms-2">Primary</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Active</span>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('admin_elements/admin_footer.php');
