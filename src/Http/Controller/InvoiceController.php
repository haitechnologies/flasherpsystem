<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Container;
use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\InvoiceService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;
use App\Helper\DateHelper;
use App\Helper\PdfGeneratorHelper;

class InvoiceController extends BaseController
{
    private InvoiceService $invoiceService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        InvoiceService $invoiceService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->invoiceService = $invoiceService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('invoices', 'Invoice');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_invoices' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_invoices' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $invoiceData = $this->buildInvoiceData($request);
        if ($request->get('save_and_send') == 1) {
            $invoiceData['invoice_status'] = 'sent';
        }
        $itemsData = $this->buildItemsData($request);

        try {
            $invoice = $this->invoiceService->getInvoice($id, $this->orgId);
            $canEditRecord = Roles::hasFullAccess($this->roleId) || $this->userId === $invoice->createdBy;
            if (!$canEditRecord) {
                log_error("IDOR attempt: User {$this->userId} tried to update invoice $id", 'WARNING', __FILE__, __LINE__);
                return new Response('Forbidden', 403);
            }

            $this->invoiceService->updateInvoice($id, $invoiceData, $itemsData, $this->orgId, $this->userId);

            updateCustomerLogs((int)($invoiceData['customer_id'] ?? 0), 'invoice', 'edit', $id);
            PdfGeneratorHelper::ensure('invoices', $id);
            flash_success('The Invoice has been updated successfully.');
            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=invoices&id=$id");
            }
            return Response::redirect('listing_invoices.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            $_SESSION['__invoices_old_input'] = $_POST;
            return Response::redirect("invoices.php?id=$id&action=edit_invoices");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'invoices',
                'module_slug' => 'invoices',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            $_SESSION['__invoices_old_input'] = $_POST;
            return Response::redirect("invoices.php?id=$id&action=edit_invoices");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $invoiceData = $this->buildInvoiceData($request);
        if ($request->get('save_and_send') == 1) {
            $invoiceData['invoice_status'] = 'sent';
        }
        $itemsData = $this->buildItemsData($request);

        try {
            $newInvoice = $this->invoiceService->createInvoice($invoiceData, $itemsData, $this->orgId, $this->userId);
            $id = $newInvoice->id;

            updateCustomerLogs((int)($invoiceData['customer_id'] ?? 0), 'invoice', 'add', $id);
            PdfGeneratorHelper::ensure('invoices', $id);
            flash_success('The Invoice has been saved successfully.');
            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=invoices&id=$id");
            }
            return Response::redirect('listing_invoices.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            $_SESSION['__invoices_old_input'] = $_POST;
            return Response::redirect("invoices.php");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'invoices',
                'module_slug' => 'invoices',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            $_SESSION['__invoices_old_input'] = $_POST;
            return Response::redirect("invoices.php");
        }
    }

    private function buildInvoiceData(Request $request): array
    {
        return [
            'customer_id' => $request->getString('customer_id'),
            'invoice_date' => $request->getString('invoice_date'),
            'expiry_date' => $request->getString('expiry_date'),
            'reference_no' => $request->getString('reference_no'),
            'warehouse_id' => $request->getString('warehouse_id'),
            'expected_shipment_date' => $request->getString('expected_shipment_date'),
            'payment_term' => $request->getString('payment_term'),
            'shipment_type' => $this->processArrayField($request->post('shipment_type', [])),
            'sales_person' => $request->getString('sales_person'),
            'job_reference_no' => $request->getString('job_reference_no'),
            'master_awb_no' => $request->getString('master_awb_no'),
            'hwb_hbol' => $request->getString('hwb_hbol'),
            'lead_id' => $request->getString('lead_id'),
            'shipper' => $request->getString('shipper'),
            'consignee' => $request->getString('consignee'),
            'origin' => $request->getString('origin'),
            'origin_country' => $request->getString('origin_country'),
            'destination' => $request->getString('destination'),
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
            'invoice_status' => $request->getString('invoice_status', 'draft'),
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
                'discount_type' => $request->getArrayItem('discount_type', $i, ''),
                'discount_type_value' => $request->getArrayItem('discount_type_value', $i, '0'),
                'discount_amount' => $request->getArrayItem('discount_amount', $i, '0'),
                'tax' => $request->getArrayItem('tax', $i, '0'),
                'tax_amount' => $request->getArrayItem('tax_amount', $i, '0'),
                'total' => $request->getArrayItem('total', $i, '0'),
            ];
        }

        return $items;
    }

    private function showForm(Request $request, int $id): Response
    {
        $module = 'invoices';
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
        $invoice_no = '';
        $invoice_status = 'draft';
        $invoice_date = DateHelper::toInputDate(date('Y-m-d'));
        $expiry_date = '';
        $reference_no = '';
        $warehouse_id = '0';
        $expected_shipment_date = '';
        $payment_term = '0';
        $shipment_type = '';
        $shipment_type_arr = [];
        $sales_person = '0';
        $job_reference_no = '';
        $master_awb_no = '';
        $hwb_hbol = '';
        $lead_id = '0';
        $shipper = '0';
        $consignee = '0';
        $origin = '0';
        $origin_country = '0';
        $destination = '0';
        $destination_country = '0';
        $no_of_packs = '0';
        $gross_weight = '0';
        $chargeable_weight = '0';
        $volume = '0';
        $cbm = '0';
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
        $discount_type_arr = [];
        $discount_type_value_arr = [];
        $discount_amount_arr = [];
        $total_rows = 1;

        $dim_pcs_arr = [];
        $dim_units_arr = [];
        $dim_length_arr = [];
        $dim_width_arr = [];
        $dim_height_arr = [];
        $dim_formula_arr = [];
        $dim_restored = false;

        if ($id > 0) {
            $created_by = 0;
            try {
                $sql = "SELECT created_by FROM `" . DB::INVOICES . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $invoice = $this->invoiceService->getInvoice($id, $this->orgId);
                    $customer_id = (string)$invoice->customerId;
                    $invoice_no = $invoice->invoiceNo;
                    $invoice_status = $invoice->invoiceStatus;
                    $invoice_date = $invoice->invoiceDate;
                    $expiry_date = $invoice->expiryDate;
                    $reference_no = (string)$invoice->referenceNo;
                    $warehouse_id = (string)$invoice->warehouseId;
                    $expected_shipment_date = (string)$invoice->expectedShipmentDate;
                    $payment_term = (string)$invoice->paymentTerm;
                    $shipment_type = (string)$invoice->shipmentType;
                    $shipment_type_arr = $shipment_type !== '' ? explode(', ', $shipment_type) : [];
                    $sales_person = (string)$invoice->salesPerson;
                    $job_reference_no = (string)$invoice->jobReferenceNo;
                    $master_awb_no = (string)$invoice->masterAwbNo;
                    $hwb_hbol = (string)$invoice->hwbHbol;
                    $lead_id = (string)$invoice->leadId;
                    $shipper = (string)$invoice->shipper;
                    $consignee = (string)$invoice->consignee;
                    $origin = (string)$invoice->origin;
                    $origin_country = (string)$invoice->originCountry;
                    $destination = (string)$invoice->destination;
                    $destination_country = (string)$invoice->destinationCountry;
                    $no_of_packs = (string)$invoice->noOfPacks;
                    $gross_weight = (string)$invoice->grossWeight;
                    $chargeable_weight = (string)$invoice->chargeableWeight;
                    $volume = (string)$invoice->volume;
                    $cbm = (string)$invoice->cbm;
                    $customer_notes = (string)$invoice->customerNotes;
                    $terms_and_conditions = (string)$invoice->termsAndConditions;
                    $grand_subtotal = (string)$invoice->grandSubtotal;
                    $grand_discount_type = (string)$invoice->grandDiscountType;
                    $grand_discount_type_value = (string)$invoice->grandDiscountTypeValue;
                    $grand_discount_amount = (string)$invoice->grandDiscountAmount;
                    $grand_after_discount = (string)$invoice->grandAfterDiscount;
                    $grand_tax = (string)$invoice->grandTax;
                    $grand_total = (string)$invoice->grandTotal;
                    $is_active = $invoice->isActive ? 1 : 0;

                    $invoice_date = DateHelper::toInputDate($invoice_date);
                    $expiry_date = ($expiry_date === '1970-01-01') ? '' : DateHelper::toDisplayDate($expiry_date);
                    $expected_shipment_date = ($expected_shipment_date === '1970-01-01') ? '' : DateHelper::toDisplayDate($expected_shipment_date);

                    $invoiceItems = $this->invoiceService->getInvoiceItems($id, $this->orgId);
                    $total_rows = count($invoiceItems);

                    foreach ($invoiceItems as $item) {
                        $item_id_arr[] = $item->id;
                        $service_arr[] = $item->service;
                        $description_arr[] = $item->description;
                        $qty_arr[] = $item->qty;
                        $rate_arr[] = $item->rate;
                        $sub_total_arr[] = $item->subTotal;
                        $tax_arr[] = $item->tax;
                        $tax_amount_arr[] = $item->taxAmount;
                        $total_arr[] = $item->total;
                        $discount_type_arr[] = $item->discountType;
                        $discount_type_value_arr[] = $item->discountTypeValue;
                        $discount_amount_arr[] = $item->discountAmount;
                    }
                } catch (\Throwable $e) {
                    log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                        'module' => 'invoices',
                        'module_slug' => 'invoices',
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
        if (isset($_SESSION['__invoices_old_input'])) {
            $old = $_SESSION['__invoices_old_input'];
            unset($_SESSION['__invoices_old_input']);

            foreach ([
                'customer_id', 'invoice_date', 'expiry_date', 'reference_no', 'warehouse_id',
                'expected_shipment_date', 'payment_term',
                'sales_person', 'job_reference_no', 'master_awb_no', 'hwb_hbol', 'lead_id',
                'shipper', 'consignee', 'origin', 'origin_country',
                'destination', 'destination_country', 'no_of_packs', 'gross_weight',
                'volume', 'chargeable_weight', 'cbm', 'terms_and_conditions',
                'grand_subtotal', 'grand_discount_type', 'grand_discount_type_value',
                'grand_discount_amount', 'grand_after_discount', 'customer_notes',
                'grand_tax', 'grand_total', 'invoice_status',
            ] as $key) {
                if (isset($old[$key])) {
                    $$key = (string)$old[$key];
                }
            }

            if (isset($old['shipment_type'])) {
                $shipment_type = is_array($old['shipment_type']) ? implode(', ', $old['shipment_type']) : $old['shipment_type'];
                $shipment_type_arr = is_array($old['shipment_type']) ? $old['shipment_type'] : explode(', ', (string)$shipment_type);
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

            if (isset($old['dim_pcs']) && is_array($old['dim_pcs'])) {
                $dim_pcs_arr = $old['dim_pcs'];
                $dim_units_arr = $old['dim_units'] ?? [];
                $dim_length_arr = $old['dim_length'] ?? [];
                $dim_width_arr = $old['dim_width'] ?? [];
                $dim_height_arr = $old['dim_height'] ?? [];
                $dim_formula_arr = $old['dim_formula'] ?? [];
                $dim_restored = true;
            }
        }

        // Fetch dropdown data
        try {
            $customersList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE is_active=1 AND approved=1 AND organization_id = :org_id ORDER BY id DESC", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $customersList = [];
            if (function_exists('log_error')) {
                log_error('invoices form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]) : ['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $orgList = $this->db->fetchAll("SELECT id, warehouse_name FROM `" . DB::ORGANIZATIONS . "` WHERE is_active=1 AND id=1");
        } catch (\Throwable $e) {
            $orgList = [];
            if (function_exists('log_error')) {
                log_error('invoices form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]) : ['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $shippersList = $this->db->fetchAll("SELECT id, shipper_name FROM `" . DB::SHIPPERS . "` WHERE is_active=1 AND organization_id = :org_id", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $shippersList = [];
            if (function_exists('log_error')) {
                log_error('invoices form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]) : ['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $consigneesList = $this->db->fetchAll("SELECT id, consignee_name FROM `" . DB::CONSIGNEES . "` WHERE is_active=1 AND organization_id = :org_id", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $consigneesList = [];
            if (function_exists('log_error')) {
                log_error('invoices form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]) : ['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $itemsList = $this->db->fetchAll("SELECT id, item_name FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' AND organization_id = :org_id ORDER BY item_name", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $itemsList = [];
            if (function_exists('log_error')) {
                log_error('invoices form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]) : ['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $countriesList = $this->db->fetchAll("SELECT id, country, abbr FROM `" . DB::GEO_COUNTRIES . "` WHERE is_active=1 ORDER BY country");
        } catch (\Throwable $e) {
            $countriesList = [];
            if (function_exists('log_error')) {
                log_error('invoices form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]) : ['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $paymentTermsList = $this->db->fetchAll("SELECT id, payment_term FROM `" . DB::PAYMENT_TERMS . "` WHERE is_active=1 AND organization_id = :org_id ORDER BY id ASC", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $paymentTermsList = [];
            if (function_exists('log_error')) {
                log_error('invoices form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]) : ['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $leadsList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::LEADS . "` WHERE is_active=1 AND organization_id = :org_id ORDER BY display_name", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $leadsList = [];
            if (function_exists('log_error')) {
                log_error('invoices form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]) : ['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]);
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
                log_error('invoices form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]) : ['module' => 'invoices', 'module_slug' => 'invoices', 'error_code' => (string)$e->getCode()]);
            }
        }

        return Response::html($this->view->render('invoices/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'customer_id' => $customer_id,
            'invoice_no' => $invoice_no,
            'invoice_status' => $invoice_status,
            'invoice_date' => $invoice_date,
            'expiry_date' => $expiry_date,
            'reference_no' => $reference_no,
            'warehouse_id' => $warehouse_id,
            'payment_term' => $payment_term,
            'expected_shipment_date' => $expected_shipment_date,
            'shipment_type' => $shipment_type,
            'shipment_type_arr' => $shipment_type_arr,
            'sales_person' => $sales_person,
            'job_reference_no' => $job_reference_no,
            'master_awb_no' => $master_awb_no,
            'hwb_hbol' => $hwb_hbol,
            'lead_id' => $lead_id,
            'shipper' => $shipper,
            'consignee' => $consignee,
            'origin' => $origin,
            'origin_country' => $origin_country,
            'destination' => $destination,
            'destination_country' => $destination_country,
            'no_of_packs' => $no_of_packs,
            'gross_weight' => $gross_weight,
            'chargeable_weight' => $chargeable_weight,
            'volume' => $volume,
            'cbm' => $cbm,
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
            'discount_type_arr' => $discount_type_arr,
            'discount_type_value_arr' => $discount_type_value_arr,
            'discount_amount_arr' => $discount_amount_arr,
            'dim_pcs_arr' => $dim_pcs_arr,
            'dim_units_arr' => $dim_units_arr,
            'dim_length_arr' => $dim_length_arr,
            'dim_width_arr' => $dim_width_arr,
            'dim_height_arr' => $dim_height_arr,
            'dim_formula_arr' => $dim_formula_arr,
            'dim_restored' => $dim_restored,
            'customersList' => $customersList,
            'orgList' => $orgList,
            'shippersList' => $shippersList,
            'consigneesList' => $consigneesList,
            'itemsList' => $itemsList,
            'countriesList' => $countriesList,
            'paymentTermsList' => $paymentTermsList,
            'leadsList' => $leadsList,
            'portsList' => $portsList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }

    private function processArrayField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $trimmed = array_map(static fn($v) => trim((string)$v), $value);
            $trimmed = array_filter($trimmed, static fn($v) => $v !== '');
            return $trimmed === [] ? null : implode(', ', $trimmed);
        }

        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }
}
