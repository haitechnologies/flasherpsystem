<?php
$pdo = new PDO('mysql:host=localhost;dbname=haizon_erp;charset=utf8mb4','root','');
$q = "SELECT module_id, module_name, module_slug FROM erp_modules WHERE module_slug LIKE '%ship%' OR module_slug IN ('ports','carriers','consignees','shippers') ORDER BY module_id";
foreach ($pdo->query($q) as $r) {
    echo $r['module_id'] . ' | ' . $r['module_name'] . ' | ' . $r['module_slug'] . PHP_EOL;
}
echo '---' . PHP_EOL;
// Check max perm id
$rp = $pdo->query("SELECT MAX(permission_id) FROM erp_module_permissions");
echo 'max perm_id: ' . $rp->fetchColumn() . PHP_EOL;
// Check max perms id  
$rpe = $pdo->query("SELECT MAX(id) FROM erp_permissions");
echo 'max perms: ' . $rpe->fetchColumn() . PHP_EOL;
// Check existing perms for role=4
$r4 = $pdo->query("SELECT DISTINCT m.module_slug FROM erp_permissions p JOIN erp_module_permissions mp ON p.permission_id = mp.permission_id JOIN erp_modules m ON mp.module_id = m.module_id WHERE p.role_id = 4");
echo 'Existing perms for role=4:' . PHP_EOL;
foreach ($r4 as $r) echo '  ' . $r['module_slug'] . PHP_EOL;
