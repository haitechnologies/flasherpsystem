<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Container;
use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\QuotationService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;
use App\Helper\DateHelper;
use App\Helper\PdfGeneratorHelper;

class QuotationController extends BaseController
{
    private QuotationService $quotationService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        QuotationService $quotationService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->quotationService = $quotationService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('quotations', 'Quotation');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_quotations' && $id > 0 && $this->canEditQuotation($id)
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_quotations' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function canEditQuotation(int $id): bool
    {
        if (Roles::hasFullAccess($this->roleId) || $this->canEdit()) {
            return true;
        }
        try {
            $row = $this->db->fetchOne(
                "SELECT created_by FROM `" . DB::QUOTATIONS . "` WHERE id = :id AND organization_id = :org_id",
                ['id' => $id, 'org_id' => $this->orgId]
            );
            return $row !== null && (int)$row['created_by'] === $this->userId;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $quotationData = $this->buildQuotationData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->quotationService->updateQuotation($id, $quotationData, $itemsData, $this->orgId, $this->userId);

            updateCustomerLogs((int)($quotationData['customer_id'] ?? 0), 'quotation', 'edit', $id);
            PdfGeneratorHelper::ensure('quotations', $id);
            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=quotations&id=$id");
            }
            flash_success('The Quotation has been updated successfully.');
            return Response::redirect('listing_quotations.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            $_SESSION['__quotations_old_input'] = $_POST;
            return Response::redirect("quotations.php?id=$id&action=edit_quotations&error_message=" . urlencode($error));
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'quotations', 'module_slug' => 'quotations',
                'stack_trace' => $e->getTraceAsString(), 'error_code' => (string)$e->getCode(),
            ]));
            $_SESSION['__quotations_old_input'] = $_POST;
            return Response::redirect("quotations.php?id=$id&action=edit_quotations&error_message=" . urlencode($e->getMessage()));
        }
    }

    private function handleCreate(Request $request): Response
    {
        $quotationData = $this->buildQuotationData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $newQuotation = $this->quotationService->createQuotation($quotationData, $itemsData, $this->orgId, $this->userId);
            $id = $newQuotation->id;

            updateCustomerLogs((int)($quotationData['customer_id'] ?? 0), 'quotation', 'add', $id);
            PdfGeneratorHelper::ensure('quotations', $id);
            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=quotations&id=$id");
            }
            flash_success('The Quotation has been saved successfully.');
            return Response::redirect('listing_quotations.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            $_SESSION['__quotations_old_input'] = $_POST;
            return Response::redirect("quotations.php?error_message=" . urlencode($error));
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'quotations', 'module_slug' => 'quotations',
                'stack_trace' => $e->getTraceAsString(), 'error_code' => (string)$e->getCode(),
            ]));
            $_SESSION['__quotations_old_input'] = $_POST;
            return Response::redirect("quotations.php?error_message=" . urlencode($e->getMessage()));
        }
    }

    private function buildQuotationData(Request $request): array
    {
        return [
            'customer_id' => $request->getString('customer_id'),
            'lead_id' => $request->getString('lead_id') !== '' ? $request->getString('lead_id') : $request->getString('lead_id_hidden'),
            'quotation_date' => $request->getString('quotation_date'),
            'expiry_date' => $request->getString('expiry_date'),
            'warehouse_id' => $request->getString('warehouse_id'),
            'expected_shipment_date' => $request->getString('expected_shipment_date'),
            'payment_term' => $request->getString('payment_term'),
            'shipment_type' => $request->getString('shipment_type'),
            'sales_person' => $request->getString('sales_person'),
            'job_reference_no' => $request->getString('job_reference_no'),
            'master_awb_no' => $request->getString('master_awb_no'),
            'hwb_hbol' => $request->getString('hwb_hbol'),
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
            'publish' => $request->has('publish') ? (bool) $request->get('publish') : false,
            'quotation_status' => $request->getString('quotation_status', 'draft'),
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
        $module = 'quotations';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $error_message = $request->getString('error_message');
        $action = $request->getString('action');

        // Default values
        $customer_id = '0';
        $lead_id = (string)$request->getInt('lead_id');
        $quotation_no = '';
        $quotation_status = 'draft';
        $quotation_date = date('d-m-Y');
        $expiry_date = '';
        $warehouse_id = '0';
        $expected_shipment_date = '';
        $payment_term = '0';
        $shipment_type = '';
        $sales_person = '0';
        $job_reference_no = '';
        $master_awb_no = '';
        $hwb_hbol = '';
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
        $publish = 1;

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
                $sql = "SELECT created_by FROM `" . DB::QUOTATIONS . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $this->canEdit() || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $quotation = $this->quotationService->getQuotation($id, $this->orgId);
                    $customer_id = (string)$quotation->customerId;
                    $lead_id = (string)$quotation->leadId;
                    $quotation_no = $quotation->quotationNo;
                    $quotation_status = $quotation->quotationStatus;
                    $quotation_date = $quotation->quotationDate;
                    $expiry_date = $quotation->expiryDate;
                    $warehouse_id = (string)$quotation->warehouseId;
                    $expected_shipment_date = (string)$quotation->expectedShipmentDate;
                    $payment_term = (string)$quotation->paymentTerm;
                    $shipment_type = (string)$quotation->shipmentType;
                    $sales_person = (string)$quotation->salesPerson;
                    $job_reference_no = (string)$quotation->jobReferenceNo;
                    $master_awb_no = (string)$quotation->masterAwbNo;
                    $hwb_hbol = (string)$quotation->hwbHbol;
                    $shipper = (string)$quotation->shipper;
                    $consignee = (string)$quotation->consignee;
                    $origin = (string)$quotation->origin;
                    $origin_country = (string)$quotation->originCountry;
                    $destination = (string)$quotation->destination;
                    $destination_country = (string)$quotation->destinationCountry;
                    $no_of_packs = (string)$quotation->noOfPacks;
                    $gross_weight = (string)$quotation->grossWeight;
                    $chargeable_weight = (string)$quotation->chargeableWeight;
                    $volume = (string)$quotation->volume;
                    $cbm = (string)$quotation->cbm;
                    $customer_notes = (string)$quotation->customerNotes;
                    $terms_and_conditions = (string)$quotation->termsAndConditions;
                    $grand_subtotal = (string)$quotation->grandSubtotal;
                    $grand_discount_type = (string)$quotation->grandDiscountType;
                    $grand_discount_type_value = (string)$quotation->grandDiscountTypeValue;
                    $grand_discount_amount = (string)$quotation->grandDiscountAmount;
                    $grand_after_discount = (string)$quotation->grandAfterDiscount;
                    $grand_tax = (string)$quotation->grandTax;
                    $grand_total = (string)$quotation->grandTotal;
                    $is_active = $quotation->isActive ? 1 : 0;
                    $publish = $quotation->publish ? 1 : 0;

                    $quotation_date = \App\Helper\DateHelper::toInputDate($quotation_date) ?: $quotation_date;
                    $expiry_date = ($expiry_date === '1970-01-01') ? '' : $expiry_date;
                    $expected_shipment_date = ($expected_shipment_date === '1970-01-01') ? '' : $expected_shipment_date;

                    $quotationItems = $this->quotationService->getQuotationItems($id, $this->orgId);
                    $total_rows = count($quotationItems);

                    foreach ($quotationItems as $item) {
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
                        'module' => 'quotations', 'module_slug' => 'quotations',
                        'stack_trace' => $e->getTraceAsString(), 'error_code' => (string)$e->getCode(),
                    ]));
                    $error_message = $e->getMessage();
                }
            }
        }

        if ($total_rows == 0) {
            $total_rows = 1;
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (isset($_SESSION['__quotations_old_input'])) {
            $old = $_SESSION['__quotations_old_input'];
            unset($_SESSION['__quotations_old_input']);

            foreach ([
                'customer_id', 'lead_id', 'quotation_date', 'expiry_date',
                'warehouse_id', 'expected_shipment_date', 'payment_term',
                'shipment_type', 'sales_person', 'job_reference_no',
                'master_awb_no', 'hwb_hbol', 'shipper', 'consignee',
                'origin', 'origin_country', 'destination', 'destination_country',
                'no_of_packs', 'gross_weight', 'chargeable_weight', 'volume',
                'terms_and_conditions', 'grand_subtotal', 'grand_discount_type',
                'grand_discount_type_value', 'grand_discount_amount',
                'grand_after_discount', 'customer_notes', 'grand_tax',
                'grand_total', 'quotation_status', 'cbm',
            ] as $key) {
                if (isset($old[$key])) {
                    $$key = (string)$old[$key];
                }
            }

            if (empty($lead_id) && isset($old['lead_id_hidden'])) {
                $lead_id = (string)$old['lead_id_hidden'];
            }

            if (isset($old['publish'])) {
                $is_active = $old['publish'] ? 1 : 0;
                $publish = $old['publish'] ? 1 : 0;
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
            $customersList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE is_active=1 AND approved=1 ORDER BY id DESC");
        } catch (\Throwable $e) {
            $customersList = [];
            if (function_exists('log_error')) {
                log_error('quotations form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]) : ['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $leadsList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::LEADS . "` WHERE is_active=1 ORDER BY display_name");
        } catch (\Throwable $e) {
            $leadsList = [];
            if (function_exists('log_error')) {
                log_error('quotations form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]) : ['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $orgList = $this->db->fetchAll("SELECT id, warehouse_name FROM `" . DB::ORGANIZATIONS . "` WHERE is_active=1 AND id=1");
        } catch (\Throwable $e) {
            $orgList = [];
            if (function_exists('log_error')) {
                log_error('quotations form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]) : ['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $shippersList = $this->db->fetchAll("SELECT id, shipper_name FROM `" . DB::SHIPPERS . "` WHERE is_active=1");
        } catch (\Throwable $e) {
            $shippersList = [];
            if (function_exists('log_error')) {
                log_error('quotations form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]) : ['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $consigneesList = $this->db->fetchAll("SELECT id, consignee_name FROM `" . DB::CONSIGNEES . "` WHERE is_active=1");
        } catch (\Throwable $e) {
            $consigneesList = [];
            if (function_exists('log_error')) {
                log_error('quotations form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]) : ['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $itemsList = $this->db->fetchAll("SELECT id, item_name FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' ORDER BY item_name");
        } catch (\Throwable $e) {
            $itemsList = [];
            if (function_exists('log_error')) {
                log_error('quotations form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]) : ['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $paymentTermsList = $this->db->fetchAll("SELECT id, payment_term FROM `" . DB::PAYMENT_TERMS . "` WHERE is_active=1 ORDER BY id");
        } catch (\Throwable $e) {
            $paymentTermsList = [];
            if (function_exists('log_error')) {
                log_error('quotations form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]) : ['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $countriesList = $this->db->fetchAll("SELECT id, country, abbr FROM `" . DB::GEO_COUNTRIES . "` WHERE is_active=1 ORDER BY country");
        } catch (\Throwable $e) {
            $countriesList = [];
            if (function_exists('log_error')) {
                log_error('quotations form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]) : ['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]);
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
                log_error('quotations form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]) : ['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $servicesList = $this->db->fetchAll("SELECT id, item_name AS service_name, unit_price AS service_rate FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' ORDER BY item_name");
        } catch (\Throwable $e) {
            $servicesList = [];
            if (function_exists('log_error')) {
                log_error('quotations form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]) : ['module' => 'quotations', 'module_slug' => 'quotations', 'error_code' => (string)$e->getCode()]);
            }
        }

        return Response::html($this->view->render('quotations/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'customer_id' => $customer_id,
            'lead_id' => $lead_id,
            'quotation_no' => $quotation_no,
            'quotation_status' => $quotation_status,
            'quotation_date' => $quotation_date,
            'expiry_date' => $expiry_date,
            'warehouse_id' => $warehouse_id,
            'expected_shipment_date' => $expected_shipment_date,
            'shipment_type' => $shipment_type,
            'sales_person' => $sales_person,
            'job_reference_no' => $job_reference_no,
            'master_awb_no' => $master_awb_no,
            'hwb_hbol' => $hwb_hbol,
            'shipper' => $shipper,
            'consignee' => $consignee,
            'origin' => $origin,
            'origin_country' => $origin_country,
            'destination' => $destination,
            'destination_country' => $destination_country,
            'payment_term' => $payment_term,
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
            'publish' => $publish,
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
            'leadsList' => $leadsList,
            'orgList' => $orgList,
            'shippersList' => $shippersList,
            'consigneesList' => $consigneesList,
            'itemsList' => $itemsList,
            'paymentTermsList' => $paymentTermsList,
            'countriesList' => $countriesList,
            'portsList' => $portsList,
            'servicesList' => $servicesList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
