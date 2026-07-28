<?php
echo "=== uaehscodes geo_countries columns ===\n";
$uae = new PDO('mysql:host=localhost;dbname=uaehscodes;charset=utf8mb4', 'root', 'hai@30');
$cols = $uae->query("SHOW COLUMNS FROM uaehs_geo_countries")->fetchAll(PDO::FETCH_COLUMN, 0);
echo "Columns: " . implode(', ', $cols) . "\n";

echo "\n=== uaehscodes geo_countries data ===\n";
$colList = implode(', ', $cols);
foreach ($uae->query("SELECT {$colList} FROM uaehs_geo_countries ORDER BY id LIMIT 10") as $r) {
    $parts = [];
    foreach ($cols as $c) $parts[] = "$c={$r[$c]}";
    echo "  " . implode(', ', $parts) . "\n";
}

echo "\n=== haizon geo_countries ===\n";
$ha = new PDO('mysql:host=localhost;dbname=haizon;charset=utf8mb4', 'root', 'hai@30');
foreach ($ha->query("SELECT id, country FROM erp_geo_countries ORDER BY id LIMIT 20") as $r) {
    echo "  id={$r['id']} country={$r['country']}\n";
}

echo "\n=== Matching uaehs country names to haizon ===\n";
$uaeNames = $uae->query("SELECT id, name FROM uaehs_geo_countries ORDER BY id");
$haCountries = [];
foreach ($ha->query("SELECT id, country FROM erp_geo_countries") as $r) {
    $haCountries[trim(strtolower($r['country']))] = (int)$r['id'];
}

$matched = 0;
$unmatched = 0;
foreach ($uaeNames as $r) {
    $uaeId = (int)$r['id'];
    $name = trim($r['name']);
    $key = strtolower($name);
    if (isset($haCountries[$key])) {
        $matched++;
    } else {
        $unmatched++;
        echo "  UNMATCHED: uaehs id=$uaeId name=$name\n";
    }
}
echo "\n  Matched: $matched, Unmatched: $unmatched\n";

