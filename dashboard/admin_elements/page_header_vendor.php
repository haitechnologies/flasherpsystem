<?php

use App\Core\DB;

$vendor_id = "";
if (isset($_REQUEST['vendor_id'])) $vendor_id = e_s__($_REQUEST['vendor_id']);
if (isset($_POST['vendor_id']))    $vendor_id = e_s__($_POST['vendor_id']);

?>
<?php
$display_name = getTableAttr('company_name', DB::VENDORS, $vendor_id);
if (empty($display_name)) $display_name = getTableAttr('display_name', DB::VENDORS, $vendor_id);
?>
<div class="page-header page-header-light shadow">
    <div class="page-header-content d-lg-flex border-top py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="vendor_overview.php?vendor_id=<?php echo $vendor_id; ?>" class="text-black"><?php echo $display_name; ?></a></h1>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
        </div>
    </div>
</div>