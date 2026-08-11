<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\VendorService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class VendorAddressController extends BaseController
{
    private VendorService $vendorService;
    private string $addressType = '';

    public function __construct(
        Database $db,
        int $userId = 0,
        int $roleId = 0,
        int $orgId = 0,
        VendorService $vendorService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->vendorService = $vendorService;
    }

    public function setAddressType(string $type): void
    {
        $this->addressType = $type;
    }

    public function __invoke(Request $request): Response
    {
        $module = $this->addressType === 'billing' ? 'vendor_billing_addresses' : 'vendor_shipping_addresses';
        $caption = $this->addressType === 'billing' ? 'Billing Address' : 'Shipping Address';
        $this->requiresModule($module, $caption);

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $vendorId = $request->getInt('vendor_id');
        if ($vendorId <= 0) {
            return Response::redirect('listing_vendors.php');
        }

        try {
            $this->vendorService->getVendor($vendorId, $this->orgId);
        } catch (NotFoundException $e) {
            return Response::redirect('listing_vendors.php');
        }

        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === "update_{$this->moduleSlug}" && $this->canEdit()
                => $this->handleUpdate($request, $vendorId),
            default => $this->showForm($request, $vendorId),
        };
    }

    private function handleUpdate(Request $request, int $vendorId): Response
    {
        $data = [
            'attention' => $request->getString('attention'),
            'country' => $request->getString('country'),
            'address_line1' => $request->getString('address_line1'),
            'address_line2' => $request->getString('address_line2'),
            'city' => $request->getString('city'),
            'state' => $request->getString('state'),
            'zipcode' => $request->getString('zipcode'),
            'phone' => $request->getString('phone'),
            'fax' => $request->getString('fax'),
        ];

        try {
            $existing = $this->db->fetchOne(
                "SELECT id FROM `" . DB::VENDOR_ADDRESSES . "` WHERE addressable_type = 'Vendor' AND addressable_id = :vendor_id AND type = :type AND organization_id = :org_id LIMIT 1",
                ['vendor_id' => $vendorId, 'type' => $this->addressType, 'org_id' => $this->orgId]
            );

            if ($existing !== null) {
                $this->vendorService->updateAddress((int)$existing['id'], $data, $this->orgId, $this->userId);
            } else {
                $this->vendorService->createAddress([
                    'vendor_id' => $vendorId,
                    'type' => $this->addressType,
                    ...$data,
                ], $this->orgId, $this->userId);
            }

            updateVendorLogs($vendorId, 'address', 'edit', 0);
            flash_success("The {$this->moduleCaption} has been updated successfully.");
            return Response::redirect("vendor_overview.php?vendor_id={$vendorId}");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            $page = $this->addressType === 'billing' ? 'vendor_billing_addresses' : 'vendor_shipping_addresses';
            return Response::redirect("{$page}.php?vendor_id={$vendorId}");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $this->moduleSlug,
                'module_slug' => $this->moduleSlug,
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            $page = $this->addressType === 'billing' ? 'vendor_billing_addresses' : 'vendor_shipping_addresses';
            flash_error($e->getMessage());
            return Response::redirect("{$page}.php?vendor_id={$vendorId}");
        }
    }

    private function showForm(Request $request, int $vendorId): Response
    {
        $module = $this->moduleSlug;
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $flashMessages = \App\Core\FlashMessage::all();
        $error_message = $request->getString('error_message');
        if (empty($error_message)) {
            foreach ($flashMessages as $fm) {
                if ($fm['type'] === 'danger') { $error_message = $fm['message']; break; }
            }
        }
        $success_message = $request->getString('success_message');
        if (empty($success_message)) {
            foreach ($flashMessages as $fm) {
                if ($fm['type'] === 'success') { $success_message = $fm['message']; break; }
            }
        }

        $attention = '';
        $country = '0';
        $address_line1 = '';
        $address_line2 = '';
        $city = '';
        $state = '';
        $zipcode = '';
        $phone = '';
        $fax = '';

        try {
            $sql = "SELECT id FROM `" . DB::VENDOR_ADDRESSES . "` WHERE addressable_type = 'Vendor' AND addressable_id = :vendor_id AND type = :type AND organization_id = :org_id LIMIT 1";
            $existing = $this->db->fetchOne($sql, ['vendor_id' => $vendorId, 'type' => $this->addressType, 'org_id' => $this->orgId]);

            if ($existing !== null) {
                $sql = "SELECT * FROM `" . DB::VENDOR_ADDRESSES . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => (int)$existing['id']]);
                if ($row !== null) {
                    $attention = $row['attention'] !== null ? (string)$row['attention'] : '';
                    $country = (string)$row['country'];
                    $address_line1 = $row['address_line1'] !== null ? (string)$row['address_line1'] : '';
                    $address_line2 = $row['address_line2'] !== null ? (string)$row['address_line2'] : '';
                    $city = $row['city'] !== null ? (string)$row['city'] : '';
                    $state = $row['state'] !== null ? (string)$row['state'] : '';
                    $zipcode = $row['zipcode'] !== null ? (string)$row['zipcode'] : '';
                    $phone = $row['phone'] !== null ? (string)$row['phone'] : '';
                    $fax = $row['fax'] !== null ? (string)$row['fax'] : '';
                }
            }
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $this->moduleSlug,
                'module_slug' => $this->moduleSlug,
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            $error_message = $e->getMessage();
        }

        return Response::html($this->view->render('vendor_addresses/form.php', [
            'vendor_id' => $vendorId,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'success_message' => $success_message,
            'attention' => $attention,
            'country' => $country,
            'address_line1' => $address_line1,
            'address_line2' => $address_line2,
            'city' => $city,
            'state' => $state,
            'zipcode' => $zipcode,
            'phone' => $phone,
            'fax' => $fax,
            'addressType' => $this->addressType,
            'canEdit' => $this->canEdit(),
        ]));
    }
}
