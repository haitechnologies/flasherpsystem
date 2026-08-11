<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\ShippingAdviceService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class ShippingAdviceController extends BaseController
{
    private ShippingAdviceService $adviceService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ShippingAdviceService $adviceService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->adviceService = $adviceService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('shipping_advices', 'Shipping Advice');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_shipping_advices' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_shipping_advices' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $adviceData = $this->buildAdviceData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->adviceService->updateAdvice($id, $adviceData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Shipping Advice has been updated successfully.');
            return Response::redirect('listing_shipping_advices.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("shipping_advices.php?id=$id&action=edit_shipping_advices");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'shipping_advices',
                'module_slug' => 'shipping_advices',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("shipping_advices.php?id=$id&action=edit_shipping_advices");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $adviceData = $this->buildAdviceData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->adviceService->createAdvice($adviceData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Shipping Advice has been saved successfully.');
            return Response::redirect('listing_shipping_advices.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("shipping_advices.php");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'shipping_advices',
                'module_slug' => 'shipping_advices',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("shipping_advices.php");
        }
    }

    private function buildAdviceData(Request $request): array
    {
        return [
            'invoice_date' => $request->getString('invoice_date'),
            'customer_id' => $request->getString('customer_id'),
            'invoice_status' => $request->getString('invoice_status', 'draft'),
            'warehouse_id' => $request->getString('warehouse_id'),
            'reference_no' => $request->getString('reference_no'),
            'awb_no' => $request->getString('awb_no'),
            'license_no' => $request->getString('license_no'),
            'mirsal_II_code' => $request->getString('mirsal_II_code'),
            'grand_total' => $request->getString('grand_total', '0.00'),
        ];
    }

    private function buildItemsData(Request $request): array
    {
        $totalRows = (int)$request->getString('total_rows', '1');
        $items = [];

        for ($i = 0; $i < $totalRows; $i++) {
            $itemId = $request->getArrayItem('item_id', $i);
            $description = $request->getArrayItem('description', $i);

            if (empty($description)) {
                continue;
            }

            $items[] = [
                'item_id' => !empty($itemId) ? (int)$itemId : null,
                'description' => $request->getArrayItem('description', $i),
                'coo' => $request->getArrayItem('coo', $i, '0'),
                'declaration_no' => $request->getArrayItem('declaration_no', $i),
                'hscode' => $request->getArrayItem('hscode', $i),
                'qty' => $request->getArrayItem('qty', $i, '1'),
                'rate' => $request->getArrayItem('rate', $i, '0'),
                'total' => $request->getArrayItem('total', $i, '0'),
            ];
        }

        return $items;
    }

    private function showForm(Request $request, int $id): Response
    {
        $module = 'shipping_advices';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $error_message = $request->getString('error_message');
        if (empty($error_message)) {
            foreach (\App\Core\FlashMessage::all() as $fm) {
                if ($fm['type'] === 'danger') { $error_message = $fm['message']; break; }
            }
        }
        $action = $request->getString('action');

        $invoice_date = date('d-m-Y');
        $customer_id = '0';
        $invoice_status = 'draft';
        $warehouse_id = '0';
        $reference_no = '';
        $awb_no = '';
        $license_no = '';
        $mirsal_II_code = '';
        $grand_total = '0.00';

        $item_id_arr = [];
        $description_arr = [];
        $coo_arr = [];
        $declaration_no_arr = [];
        $hscode_arr = [];
        $qty_arr = [];
        $rate_arr = [];
        $total_arr = [];
        $total_rows = 1;

        if ($id > 0) {
            $created_by = 0;
            try {
                $sql = "SELECT created_by FROM `" . DB::SHIPPING_ADVICES . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $advice = $this->adviceService->getAdvice($id, $this->orgId);
                    $invoice_date = DateHelper::toDisplayDate($advice->invoiceDate) ?: $advice->invoiceDate;
                    $customer_id = (string)$advice->customerId;
                    $invoice_status = $advice->invoiceStatus;
                    $warehouse_id = (string)$advice->warehouseId;
                    $reference_no = (string)$advice->referenceNo;
                    $awb_no = (string)$advice->awbNo;
                    $license_no = (string)$advice->licenseNo;
                    $mirsal_II_code = (string)$advice->mirsalIICode;
                    $grand_total = (string)$advice->grandTotal;

                    $items = $this->adviceService->getAdviceItems($id, $this->orgId);
                    $total_rows = count($items);

                    foreach ($items as $item) {
                        $item_id_arr[] = $item->id;
                        $description_arr[] = $item->description;
                        $coo_arr[] = $item->coo;
                        $declaration_no_arr[] = $item->declarationNo;
                        $hscode_arr[] = $item->hscode;
                        $qty_arr[] = $item->qty;
                        $rate_arr[] = $item->rate;
                        $total_arr[] = $item->total;
                    }
                } catch (\Throwable $e) {
                    log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                        'module' => 'shipping_advices',
                        'module_slug' => 'shipping_advices',
                        'stack_trace' => $e->getTraceAsString(),
                        'error_code' => (string)$e->getCode(),
                    ]));
                    $error_message = $e->getMessage();
                }
            }
        }

        if ($total_rows == 0) {
            $total_rows = 1;
        }

        $customersList = [];
        $warehousesList = [];
        $countriesList = [];
        try {
            $customersList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE entity_type = 'shipping' AND is_active=1 AND approved=1 ORDER BY id DESC");
        } catch (\Throwable $e) {
        }
        try {
            $warehousesList = $this->db->fetchAll("SELECT id, warehouse_name FROM `" . DB::WAREHOUSES . "` WHERE is_active=1 ORDER BY warehouse_name ASC");
        } catch (\Throwable $e) {
        }
        try {
            $countriesList = $this->db->fetchAll("SELECT id, country, abbr FROM `" . DB::GEO_COUNTRIES . "` WHERE is_active=1 ORDER BY country");
        } catch (\Throwable $e) {
        }

        return Response::html($this->view->render('shipping_advices/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'invoice_date' => $invoice_date,
            'customer_id' => $customer_id,
            'invoice_status' => $invoice_status,
            'warehouse_id' => $warehouse_id,
            'reference_no' => $reference_no,
            'awb_no' => $awb_no,
            'license_no' => $license_no,
            'mirsal_II_code' => $mirsal_II_code,
            'grand_total' => $grand_total,
            'total_rows' => $total_rows,
            'item_id_arr' => $item_id_arr,
            'description_arr' => $description_arr,
            'coo_arr' => $coo_arr,
            'declaration_no_arr' => $declaration_no_arr,
            'hscode_arr' => $hscode_arr,
            'qty_arr' => $qty_arr,
            'rate_arr' => $rate_arr,
            'total_arr' => $total_arr,
            'customersList' => $customersList,
            'warehousesList' => $warehousesList,
            'countriesList' => $countriesList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
