<?php

declare(strict_types=1);

use App\Http\Controller\VendorAddressController;
use App\Http\Request;

require_once __DIR__ . '/bootstrap.php';

$activeOrganizationId = dashboardRequireActiveOrganization();

$addressType = 'billing';
$controller = $container->get(VendorAddressController::class);
$controller->setAddressType($addressType);
$response = $controller(Request::fromGlobals());
$response->send();
