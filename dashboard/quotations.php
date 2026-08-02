<?php

declare(strict_types=1);

use App\Http\Controller\QuotationController;
use App\Http\Request;

require_once __DIR__ . '/bootstrap.php';

$activeOrganizationId = dashboardRequireActiveOrganization();
$controller = $container->get(QuotationController::class);
$response = $controller(Request::fromGlobals());

$body = $response->getBody();
$headers = $response->getHeaders();

$isRedirect = array_key_exists('Location', $headers);

if ($isRedirect) {
    $response->send();
    exit;
}

include 'admin_elements/admin_header.php';
echo $body;
include 'admin_elements/admin_footer.php';
