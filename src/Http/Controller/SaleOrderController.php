<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Container;
use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\SaleOrderService;
use App\Security\Roles;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Helper\DateHelper;
use App\Helper\PdfGeneratorHelper;

class SaleOrderController extends BaseController
{
    private SaleOrderService $saleOrderService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        SaleOrderService $saleOrderService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->saleOrderService = $saleOrderService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('sale_orders', 'Sale Order');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_sale_orders' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_sale_orders' && $this->canCreate()
                => $this->handleCreate($request),
            $request->isPost() && $action === 'convert_quotation_to_sale_orders' && $this->canCreate()
                => $this->handleConvertQuotation($request),
            $request->isPost() && $action === 'convert_sale_orders' && $id > 0 && $this->canEdit()
                => $this->handleConvertToInvoice($request, $id),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $saleOrderData = $this->buildSaleOrderData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->saleOrderService->updateSaleOrder($id, $saleOrderData, $itemsData, $this->orgId, $this->userId);

            PdfGeneratorHelper::ensure('sale_orders', $id);

            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=sale_orders&id=$id");
            }
            flash_success('The Sale Order has been updated successfully.');
            return Response::redirect('listing_sale_orders.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            $_SESSION['__sale_orders_old_input'] = $_POST;
            return Response::redirect("sale_orders.php?id=$id&action=edit_sale_orders");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'sale_orders',
                'module_slug' => 'sale_orders',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            $_SESSION['__sale_orders_old_input'] = $_POST;
            return Response::redirect("sale_orders.php?id=$id&action=edit_sale_orders");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $saleOrderData = $this->buildSaleOrderData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $newSaleOrder = $this->saleOrderService->createSaleOrder($saleOrderData, $itemsData, $this->orgId, $this->userId);
            $id = $newSaleOrder->id;

            PdfGeneratorHelper::ensure('sale_orders', $id);

            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=sale_orders&id=$id");
            }
            flash_success('The Sale Order has been saved successfully.');
            return Response::redirect('listing_sale_orders.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            $_SESSION['__sale_orders_old_input'] = $_POST;
            return Response::redirect("sale_orders.php");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'sale_orders',
                'module_slug' => 'sale_orders',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            $_SESSION['__sale_orders_old_input'] = $_POST;
            return Response::redirect("sale_orders.php");
        }
    }

    private function handleConvertQuotation(Request $request): Response
    {
        $quotationId = $request->getInt('quotation_id');

        try {
            $saleOrder = $this->saleOrderService->convertFromQuotation($quotationId, $this->orgId, $this->userId);
            flash_success('The Sale Order has been created from Quotation successfully.');
            return Response::redirect("sale_order_overview.php?sale_order_id={$saleOrder->id}");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("quotation_overview.php?quotation_id=$quotationId");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("quotation_overview.php?quotation_id=$quotationId");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'sale_orders',
                'module_slug' => 'sale_orders',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("quotation_overview.php?quotation_id=$quotationId");
        }
    }

    private function handleConvertToInvoice(Request $request, int $id): Response
    {
        try {
            $result = $this->saleOrderService->convertToInvoice($id, $this->orgId, $this->userId);
            flash_success('This Sale Order has been Converted to Invoice Successfully. <a href="invoice_overview.php?invoice_id=' . $result['invoice_id'] . '">' . $result['invoice_no'] . '</a>');
            return Response::redirect("invoice_overview.php?invoice_id={$result['invoice_id']}");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect('listing_sale_orders.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("sale_order_overview.php?sale_order_id=$id");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'sale_orders',
                'module_slug' => 'sale_orders',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("sale_order_overview.php?sale_order_id=$id");
        }
    }

    private function buildSaleOrderData(Request $request): array
    {
        return [
            'customer_id' => $request->getString('customer_id'),
            'sale_order_date' => $request->getString('sale_order_date'),
            'expiry_date' => $request->getString('expiry_date'),
            'reference_no' => $request->getString('reference_no'),
            'warehouse_id' => $request->getString('warehouse_id'),
            'expected_shipment_date' => $request->getString('expected_shipment_date'),
            'payment_term' => $request->getString('payment_term'),
            'shipment_type' => $request->getString('shipment_type'),
            'sales_person' => $request->getString('sales_person'),
            'job_reference_no' => $request->getString('job_reference_no'),
            'master_awb_no' => $request->getString('master_awb_no'),
            'mawb_bol' => $request->getString('mawb_bol'),
            'hwb_hbol' => $request->getString('hwb_hbol'),
            'shipper_id' => $request->getString('shipper_id'),
            'consignee_id' => $request->getString('consignee_id'),
            'origin_port' => $request->getString('origin_port'),
            'origin_country' => $request->getString('origin_country'),
            'destination_port' => $request->getString('destination_port'),
            'destination_country' => $request->getString('destination_country'),
            'no_of_packs' => $request->getString('no_of_packs'),
            'gross_weight' => $request->getString('gross_weight'),
            'chargeable_weight' => $request->getString('chargeable_weight'),
            'volume' => $request->getString('volume'),
            'cbm' => $request->getString('cbm'),
            'terms_and_conditions' => $request->getString('terms_and_conditions'),
            'grand_subtotal' => $request->getString('grand_subtotal'),
            'grand_discount_type' => $request->getString('grand_discount_type'),
            'grand_discount_type_value' => $request->getString('grand_discount_type_value'),
            'grand_discount_amount' => $request->getString('grand_discount_amount'),
            'grand_after_discount' => $request->getString('grand_after_discount'),
            'customer_notes' => $request->getString('customer_notes'),
            'grand_tax' => $request->getString('grand_tax'),
            'grand_total' => $request->getString('grand_total'),
            'publish' => true,
            'is_active' => true,
            'sale_order_status' => $request->getString('sale_order_status', 'draft'),
        ];
    }

    private function buildItemsData(Request $request): array
    {
        $totalRows = (int)$request->getString('total_rows', '1');
        $items = [];

        for ($i = 0; $i < $totalRows; $i++) {
            $service = $request->getArrayItem('service', $i);
            $itemId = $request->getArrayItem('item_id', $i);

            if (empty($service) || (int)$service <= 0) {
                continue;
            }

            $items[] = [
                'id' => !empty($itemId) ? (int)$itemId : null,
                'service' => (int)$service,
                'description' => $request->getArrayItem('description', $i),
                'qty' => $request->getArrayItem('qty', $i, '1'),
                'rate' => $request->getArrayItem('rate', $i, '0'),
                'sub_total' => $request->getArrayItem('sub_total', $i, '0'),
                'tax' => $request->getArrayItem('tax', $i, '0'),
                'tax_amount' => $request->getArrayItem('tax_amount', $i, '0'),
                'total' => $request->getArrayItem('total', $i, '0'),
            ];
        }

        return $items;
    }

    private function showForm(Request $request, int $id): Response
    {
        $module = 'sale_orders';
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

        // Default values
        $customer_id = '0';
        $sale_order_no = '';
        $sale_order_status = 'draft';
        $sale_order_date = date('Y-m-d');
        $expiry_date = '';
        $reference_no = '';
        $warehouse_id = '0';
        $expected_shipment_date = '';
        $payment_term = '0';
        $shipment_type = '';
        $sales_person = '0';
        $job_reference_no = '';
        $master_awb_no = '';
        $shipper = '0';
        $consignee = '0';
        $origin = '0';
        $destination = '0';
        $no_of_packs = '0';
        $gross_weight = '0';
        $chargeable_weight = '0';
        $volume = '0';
        $cbm = '0';
        $mawb_bol = '';
        $hwb_hbol = '';
        $origin_port = '0';
        $origin_country = '0';
        $destination_port = '0';
        $destination_country = '0';
        $invoice_id = '';
        $quotation_id = '';
        $terms_and_conditions = '';
        $grand_subtotal = '0.00';
        $grand_discount_type = '';
        $grand_discount_type_value = '';
        $grand_discount_amount = '';
        $grand_after_discount = '';
        $customer_notes = '';
        $grand_tax = '0.00';
        $grand_total = '0.00';
        $is_active = 1;

        $item_id_arr = [];
        $service_arr = [];
        $description_arr = [];
        $qty_arr = [];
        $rate_arr = [];
        $sub_total_arr = [];
        $tax_arr = [];
        $tax_amount_arr = [];
        $total_arr = [];
        $total_rows = 1;

        if ($id > 0) {
            $created_by = 0;
            try {
                $sql = "SELECT created_by FROM `" . DB::SALE_ORDERS . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $this->canEdit() || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $saleOrder = $this->saleOrderService->getSaleOrder($id, $this->orgId);
                    $customer_id = (string)$saleOrder->customerId;
                    $sale_order_no = $saleOrder->saleOrderNo;
                    $sale_order_status = $saleOrder->saleOrderStatus;
                    $sale_order_date = $saleOrder->saleOrderDate;
                    $expiry_date = $saleOrder->expiryDate;
                    $reference_no = (string)$saleOrder->referenceNo;
                    $warehouse_id = (string)$saleOrder->warehouseId;
                    $expected_shipment_date = (string)$saleOrder->expectedShipmentDate;
                    $payment_term = (string)$saleOrder->paymentTerm;
                    $shipment_type = (string)$saleOrder->shipmentType;
                    $sales_person = (string)$saleOrder->salesPerson;
                    $job_reference_no = (string)$saleOrder->jobReferenceNo;
                    $master_awb_no = (string)$saleOrder->masterAwbNo;
                    $shipper = (string)$saleOrder->shipperId;
                    $consignee = (string)$saleOrder->consigneeId;
                    $origin = (string)$saleOrder->originPort;
                    $destination = (string)$saleOrder->destinationPort;
                    $no_of_packs = (string)$saleOrder->noOfPacks;
                    $gross_weight = (string)$saleOrder->grossWeight;
                    $chargeable_weight = (string)$saleOrder->chargeableWeight;
                    $volume = (string)$saleOrder->volume;
                    $cbm = (string)$saleOrder->cbm;
                    $mawb_bol = (string)$saleOrder->mawbBol;
                    $hwb_hbol = (string)$saleOrder->hwbHbol;
                    $origin_port = (string)$saleOrder->originPort;
                    $origin_country = (string)$saleOrder->originCountry;
                    $destination_port = (string)$saleOrder->destinationPort;
                    $destination_country = (string)$saleOrder->destinationCountry;
                    $invoice_id = $saleOrder->invoiceId !== null ? (string)$saleOrder->invoiceId : '';
                    $quotation_id = $saleOrder->quotationId !== null ? (string)$saleOrder->quotationId : '';
                    $customer_notes = (string)$saleOrder->customerNotes;
                    $terms_and_conditions = (string)$saleOrder->termsAndConditions;
                    $grand_subtotal = (string)$saleOrder->grandSubtotal;
                    $grand_discount_type = (string)$saleOrder->grandDiscountType;
                    $grand_discount_type_value = (string)$saleOrder->grandDiscountTypeValue;
                    $grand_discount_amount = (string)$saleOrder->grandDiscountAmount;
                    $grand_after_discount = (string)$saleOrder->grandAfterDiscount;
                    $grand_tax = (string)$saleOrder->grandTax;
                    $grand_total = (string)$saleOrder->grandTotal;
                    $is_active = $saleOrder->isActive ? 1 : 0;

                    $sale_order_date = \App\Helper\DateHelper::toDisplayDate($sale_order_date);
                    $expiry_date = ($expiry_date === '1970-01-01') ? '' : DateHelper::toDisplayDate($expiry_date);
                    $expected_shipment_date = ($expected_shipment_date === '1970-01-01') ? '' : DateHelper::toDisplayDate($expected_shipment_date);

                    $saleOrderItems = $this->saleOrderService->getSaleOrderItems($id, $this->orgId);
                    $total_rows = count($saleOrderItems);

                    foreach ($saleOrderItems as $item) {
                        $item_id_arr[] = $item->id;
                        $service_arr[] = $item->service;
                        $description_arr[] = $item->description;
                        $qty_arr[] = $item->qty;
                        $rate_arr[] = $item->rate;
                        $sub_total_arr[] = $item->subTotal;
                        $tax_arr[] = $item->tax;
                        $tax_amount_arr[] = $item->taxAmount;
                        $total_arr[] = $item->total;
                    }
                } catch (\Throwable $e) {
                    log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                        'module' => 'sale_orders',
                        'module_slug' => 'sale_orders',
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

        // Restore old form input after failed save
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (isset($_SESSION['__sale_orders_old_input'])) {
            $old = $_SESSION['__sale_orders_old_input'];
            unset($_SESSION['__sale_orders_old_input']);

            foreach ([
                'customer_id', 'sale_order_date', 'expiry_date', 'reference_no', 'warehouse_id',
                'expected_shipment_date', 'payment_term', 'shipment_type',
                'sales_person', 'job_reference_no', 'master_awb_no',
                'hwb_hbol', 'origin_country', 'destination_country',
                'no_of_packs', 'gross_weight', 'chargeable_weight', 'volume',
                'cbm', 'terms_and_conditions', 'grand_subtotal',
                'grand_discount_type', 'grand_discount_type_value',
                'grand_discount_amount', 'grand_after_discount', 'customer_notes',
                'grand_tax', 'grand_total', 'sale_order_status',
            ] as $key) {
                if (isset($old[$key])) {
                    $$key = (string)$old[$key];
                }
            }

            $remap = ['shipper_id' => 'shipper', 'consignee_id' => 'consignee',
                       'origin_port' => 'origin', 'destination_port' => 'destination'];
            foreach ($remap as $postKey => $varName) {
                if (isset($old[$postKey])) {
                    $$varName = (string)$old[$postKey];
                }
            }

            if (isset($old['publish'])) {
                $is_active = $old['publish'] ? 1 : 0;
            }

            if (isset($old['service']) && is_array($old['service'])) {
                $item_id_arr = $old['item_id'] ?? [];
                $service_arr = $old['service'];
                $description_arr = $old['description'] ?? [];
                $qty_arr = $old['qty'] ?? [];
                $rate_arr = $old['rate'] ?? [];
                $sub_total_arr = $old['sub_total'] ?? [];
                $tax_arr = $old['tax'] ?? [];
                $tax_amount_arr = $old['tax_amount'] ?? [];
                $total_arr = $old['total'] ?? [];
                $total_rows = max(1, count($service_arr));
            }
        }

        // Fetch dropdown data
        try {
            $customersList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE is_active=1 AND approved=1 AND organization_id = :org_id ORDER BY id DESC", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $customersList = [];
            if (function_exists('log_error')) {
                log_error('sale_orders form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]) : ['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $orgList = $this->db->fetchAll("SELECT id, warehouse_name FROM `" . DB::ORGANIZATIONS . "` WHERE is_active=1 AND id=1");
        } catch (\Throwable $e) {
            $orgList = [];
            if (function_exists('log_error')) {
                log_error('sale_orders form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]) : ['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $shippersList = $this->db->fetchAll("SELECT id, shipper_name FROM `" . DB::SHIPPERS . "` WHERE is_active=1 AND organization_id = :org_id", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $shippersList = [];
            if (function_exists('log_error')) {
                log_error('sale_orders form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]) : ['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $consigneesList = $this->db->fetchAll("SELECT id, consignee_name FROM `" . DB::CONSIGNEES . "` WHERE is_active=1 AND organization_id = :org_id", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $consigneesList = [];
            if (function_exists('log_error')) {
                log_error('sale_orders form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]) : ['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $itemsList = $this->db->fetchAll("SELECT id, item_name, unit_price FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' AND organization_id = :org_id ORDER BY item_name", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $itemsList = [];
            if (function_exists('log_error')) {
                log_error('sale_orders form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]) : ['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $paymentTermsList = $this->db->fetchAll("SELECT id, payment_term FROM `" . DB::PAYMENT_TERMS . "` WHERE publish=1 ORDER BY id");
        } catch (\Throwable $e) {
            $paymentTermsList = [];
            if (function_exists('log_error')) {
                log_error('sale_orders form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]) : ['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $countriesList = $this->db->fetchAll("SELECT id, country, abbr FROM `" . DB::GEO_COUNTRIES . "` WHERE is_active=1 ORDER BY country");
        } catch (\Throwable $e) {
            $countriesList = [];
            if (function_exists('log_error')) {
                log_error('sale_orders form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]) : ['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $portsList = $this->db->fetchAll("SELECT id, port_name, port_code, country_id FROM `" . DB::PORTS . "` WHERE is_active=1 ORDER BY port_name LIMIT 50");
            foreach ([$origin, $destination] as $selectedPortId) {
                if (!is_numeric($selectedPortId) || (int)$selectedPortId <= 0) {
                    continue;
                }
                $found = false;
                foreach ($portsList as $portRow) {
                    if ((string)$portRow['id'] === (string)$selectedPortId) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $selectedPort = $this->db->fetchOne("SELECT id, port_name, port_code, country_id FROM `" . DB::PORTS . "` WHERE id = :id AND is_active = 1", ['id' => (int)$selectedPortId]);
                    if ($selectedPort) {
                        $portsList[] = $selectedPort;
                    }
                }
            }
        } catch (\Throwable $e) {
            $portsList = [];
            if (function_exists('log_error')) {
                log_error('sale_orders form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]) : ['module' => 'sale_orders', 'module_slug' => 'sale_orders', 'error_code' => (string)$e->getCode()]);
            }
        }

        return Response::html($this->view->render('sale_orders/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'customer_id' => $customer_id,
            'sale_order_no' => $sale_order_no,
            'sale_order_status' => $sale_order_status,
            'sale_order_date' => $sale_order_date,
            'expiry_date' => $expiry_date,
            'reference_no' => $reference_no,
            'warehouse_id' => $warehouse_id,
            'expected_shipment_date' => $expected_shipment_date,
            'shipment_type' => $shipment_type,
            'sales_person' => $sales_person,
            'job_reference_no' => $job_reference_no,
            'master_awb_no' => $master_awb_no,
            'shipper' => $shipper,
            'consignee' => $consignee,
            'origin' => $origin,
            'destination' => $destination,
            'no_of_packs' => $no_of_packs,
            'gross_weight' => $gross_weight,
            'chargeable_weight' => $chargeable_weight,
            'volume' => $volume,
            'cbm' => $cbm,
            'mawb_bol' => $mawb_bol,
            'hwb_hbol' => $hwb_hbol,
            'origin_port' => $origin_port,
            'origin_country' => $origin_country,
            'destination_port' => $destination_port,
            'destination_country' => $destination_country,
            'invoice_id' => $invoice_id,
            'quotation_id' => $quotation_id,
            'payment_term' => $payment_term,
            'terms_and_conditions' => $terms_and_conditions,
            'grand_subtotal' => $grand_subtotal,
            'grand_discount_type' => $grand_discount_type,
            'grand_discount_type_value' => $grand_discount_type_value,
            'grand_discount_amount' => $grand_discount_amount,
            'grand_after_discount' => $grand_after_discount,
            'customer_notes' => $customer_notes,
            'grand_tax' => $grand_tax,
            'grand_total' => $grand_total,
            'is_active' => $is_active,
            'total_rows' => $total_rows,
            'item_id_arr' => $item_id_arr,
            'service_arr' => $service_arr,
            'description_arr' => $description_arr,
            'qty_arr' => $qty_arr,
            'rate_arr' => $rate_arr,
            'sub_total_arr' => $sub_total_arr,
            'tax_arr' => $tax_arr,
            'tax_amount_arr' => $tax_amount_arr,
            'total_arr' => $total_arr,
            'customersList' => $customersList,
            'orgList' => $orgList,
            'shippersList' => $shippersList,
            'consigneesList' => $consigneesList,
            'itemsList' => $itemsList,
            'paymentTermsList' => $paymentTermsList,
            'countriesList' => $countriesList,
            'portsList' => $portsList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
