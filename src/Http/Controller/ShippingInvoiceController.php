<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\ShippingInvoiceService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class ShippingInvoiceController extends BaseController
{
    private ShippingInvoiceService $invoiceService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ShippingInvoiceService $invoiceService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->invoiceService = $invoiceService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('shipping_invoices', 'Shipping Invoice');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_shipping_invoices' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_shipping_invoices' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $invoiceData = $this->buildInvoiceData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->invoiceService->updateInvoice($id, $invoiceData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Shipping Invoice has been updated successfully.');
            return Response::redirect('listing_shipping_invoices.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("shipping_invoices.php?id=$id&action=edit_shipping_invoices");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'shipping_invoices',
                'module_slug' => 'shipping_invoices',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("shipping_invoices.php?id=$id&action=edit_shipping_invoices");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $invoiceData = $this->buildInvoiceData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->invoiceService->createInvoice($invoiceData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Shipping Invoice has been saved successfully.');
            return Response::redirect('listing_shipping_invoices.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("shipping_invoices.php");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'shipping_invoices',
                'module_slug' => 'shipping_invoices',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("shipping_invoices.php");
        }
    }

    private function buildInvoiceData(Request $request): array
    {
        return [
            'invoice_date' => $request->getString('invoice_date'),
            'reference_no' => $request->getString('reference_no'),
            'customer_id' => $request->getString('customer_id'),
            'invoice_status' => $request->getString('invoice_status', 'draft'),
            'warehouse_id' => $request->getString('warehouse_id'),
            'no_of_packs' => $request->getString('no_of_packs'),
            'gross_weight' => $request->getString('gross_weight'),
            'master_awb_no' => $request->getString('master_awb_no'),
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
                'origin' => $request->getArrayItem('coo', $i, '0'),
                'declaration_no' => $request->getArrayItem('declaration_no', $i),
                'hs_code' => $request->getArrayItem('hscode', $i),
                'qty' => $request->getArrayItem('qty', $i, '1'),
                'unit_price' => $request->getArrayItem('rate', $i, '0'),
                'total_amount' => $request->getArrayItem('total', $i, '0'),
            ];
        }

        return $items;
    }

    private function showForm(Request $request, int $id): Response
    {
        $module = 'shipping_invoices';
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
        $reference_no = '';
        $customer_id = '0';
        $invoice_status = 'draft';
        $warehouse_id = '0';
        $no_of_packs = '';
        $gross_weight = '';
        $master_awb_no = '';
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
                $sql = "SELECT created_by FROM `" . DB::SHIPPING_INVOICES . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $invoice = $this->invoiceService->getInvoice($id, $this->orgId);
                    $invoice_date = DateHelper::toDisplayDate($invoice->invoiceDate) ?: $invoice->invoiceDate;
                    $reference_no = (string)$invoice->referenceNo;
                    $customer_id = (string)$invoice->customerId;
                    $invoice_status = $invoice->invoiceStatus;
                    $warehouse_id = (string)$invoice->warehouseId;
                    $no_of_packs = (string)$invoice->noOfPacks;
                    $gross_weight = (string)$invoice->grossWeight;
                    $master_awb_no = (string)$invoice->masterAwbNo;
                    $grand_total = (string)$invoice->grandTotal;

                    $items = $this->invoiceService->getInvoiceItems($id, $this->orgId);
                    $total_rows = count($items);

                    foreach ($items as $item) {
                        $item_id_arr[] = $item->id;
                        $description_arr[] = $item->description;
                        $coo_arr[] = $item->origin;
                        $declaration_no_arr[] = $item->declarationNo;
                        $hscode_arr[] = $item->hsCode;
                        $qty_arr[] = $item->qty;
                        $rate_arr[] = $item->unitPrice;
                        $total_arr[] = $item->totalAmount;
                    }
                } catch (\Throwable $e) {
                    log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                        'module' => 'shipping_invoices',
                        'module_slug' => 'shipping_invoices',
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

        return Response::html($this->view->render('shipping_invoices/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'invoice_date' => $invoice_date,
            'reference_no' => $reference_no,
            'customer_id' => $customer_id,
            'invoice_status' => $invoice_status,
            'warehouse_id' => $warehouse_id,
            'no_of_packs' => $no_of_packs,
            'gross_weight' => $gross_weight,
            'master_awb_no' => $master_awb_no,
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
