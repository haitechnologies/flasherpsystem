<?php
require dirname(__DIR__) . '/config/database.php';

$mysqli->query("ALTER TABLE erp_items DROP COLUMN category_id");
echo "Dropped category_id\n";

$mysqli->query("ALTER TABLE erp_items DROP COLUMN description");
echo "Dropped description\n";

$mysqli->query("ALTER TABLE erp_items DROP COLUMN required_documents");
echo "Dropped required_documents\n";

$mysqli->query("ALTER TABLE erp_items DROP COLUMN steps");
echo "Dropped steps\n";

echo "Done.\n";
