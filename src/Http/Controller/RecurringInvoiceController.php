<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\RecurringInvoiceService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Helper\DateHelper;
use App\Helper\PdfGeneratorHelper;

class RecurringInvoiceController extends BaseController
{
    private RecurringInvoiceService $invoiceService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        RecurringInvoiceService $invoiceService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->invoiceService = $invoiceService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('recurring_invoices', 'Recurring Invoice');

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
        $itemsData = $this->buildItemsData($request);

        try {
            $this->invoiceService->updateInvoice($id, $invoiceData, $itemsData, $this->orgId, $this->userId);
            PdfGeneratorHelper::ensure('recurring_invoices', $id);
            $saveAndSend = $request->getString('save_and_send');
            if ($saveAndSend === '1') {
                return Response::redirect("send_email.php?current_module=recurring_invoices&id=$id");
            }
            flash_success('The Recurring Invoice has been updated successfully.');
            return Response::redirect('listing_recurring_invoices.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("recurring_invoices.php?id=$id&action=edit_invoices");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'recurring_invoices',
                'module_slug' => 'recurring_invoices',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("recurring_invoices.php?id=$id&action=edit_invoices");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $invoiceData = $this->buildInvoiceData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $invoice = $this->invoiceService->createInvoice($invoiceData, $itemsData, $this->orgId, $this->userId);
            PdfGeneratorHelper::ensure('recurring_invoices', $invoice->id);
            $saveAndSend = $request->getString('save_and_send');
            if ($saveAndSend === '1') {
                return Response::redirect("send_email.php?current_module=recurring_invoices&id=" . $invoice->id);
            }
            flash_success('The Recurring Invoice has been saved successfully.');
            return Response::redirect('listing_recurring_invoices.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("recurring_invoices.php");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'recurring_invoices',
                'module_slug' => 'recurring_invoices',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("recurring_invoices.php");
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
            'profile_name' => $request->getString('profile_name'),
            'frequency' => $request->getString('frequency'),
            'start_date' => $request->getString('start_date'),
            'end_date' => $request->getString('end_date'),
            'expected_shipment_date' => $request->getString('expected_shipment_date'),
            'payment_term' => $request->getString('payment_term'),
            'shipment_type' => $request->getString('shipment_type'),
            'sales_person' => $request->getString('sales_person'),
            'job_reference_no' => $request->getString('job_reference_no'),
            'master_awb_no' => $request->getString('master_awb_no'),
            'shipper' => $request->getString('shipper'),
            'consignee' => $request->getString('consignee'),
            'origin' => $request->getString('origin'),
            'destination' => $request->getString('destination'),
            'no_of_packs' => $request->getString('no_of_packs'),
            'gross_weight' => $request->getString('gross_weight'),
            'chargeable_weight' => $request->getString('chargeable_weight'),
            'volume' => $request->getString('volume'),
            'customer_notes' => $request->getString('customer_notes'),
            'terms_and_conditions' => $request->getString('terms_and_conditions'),
            'grand_subtotal' => $request->getString('grand_subtotal'),
            'grand_discount_type' => $request->getString('grand_discount_type'),
            'grand_discount_type_value' => $request->getString('grand_discount_type_value'),
            'grand_discount_amount' => $request->getString('grand_discount_amount'),
            'grand_after_discount' => $request->getString('grand_after_discount'),
            'grand_tax' => $request->getString('grand_tax'),
            'grand_total' => $request->getString('grand_total'),
            'invoice_status' => $request->getString('invoice_status', 'draft'),
            'publish' => $request->get('publish') ? 1 : 0,
            'save_and_send' => $request->getString('save_and_send'),
        ];
    }

    private function buildItemsData(Request $request): array
    {
        $totalRows = (int)$request->getString('total_rows', '1');
        $items = [];

        for ($i = 0; $i < $totalRows; $i++) {
            $itemId = $request->getArrayItem('item_id', $i);
            $service = $request->getArrayItem('service', $i);

            if (empty($service) || (int)$service <= 0) {
                continue;
            }

            $items[] = [
                'item_id' => !empty($itemId) ? (int)$itemId : null,
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
        $module = 'recurring_invoices';
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

        $customer_id = '0';
        $invoice_date = date('d-m-Y');
        $expiry_date = '';
        $invoice_status = '';
        $reference_no = '';
        $warehouse_id = '';
        $profile_name = '';
        $frequency = 'monthly';
        $start_date = date('d-m-Y');
        $end_date = '';
        $expected_shipment_date = '';
        $payment_term = '';
        $shipment_type = '';
        $sales_person = '';
        $job_reference_no = '';
        $master_awb_no = '';
        $shipper = '';
        $consignee = '';
        $origin = '';
        $destination = '';
        $no_of_packs = '';
        $gross_weight = '';
        $chargeable_weight = '';
        $volume = '';
        $customer_notes = '';
        $terms_and_conditions = '';
        $grand_subtotal = '';
        $grand_discount_type = '';
        $grand_discount_type_value = '';
        $grand_discount_amount = '';
        $grand_after_discount = '';
        $grand_tax = '';
        $grand_total = '';
        $publish = 0;

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

        $source_invoice_id = $request->getInt('source_invoice_id');

        if ($source_invoice_id > 0 && $id <= 0 && !$request->isPost()) {
            try {
                $sql = "SELECT * FROM `" . DB::INVOICES . "` WHERE id = :id AND organization_id = :org_id";
                $source = $this->db->fetchOne($sql, ['id' => $source_invoice_id, 'org_id' => $this->orgId]);
                if ($source) {
                    $customer_id = (string)($source['customer_id'] ?? '0');
                    $invoice_date = date('d-m-Y');
                    $expiry_date = '';
                    $reference_no = (string)($source['reference_no'] ?? '');
                    $warehouse_id = (string)($source['warehouse_id'] ?? '');
                    $profile_name = 'Recurring - ' . ($source['customer_id'] ?? 'Customer');
                    $frequency = 'monthly';
                    $start_date = date('d-m-Y');
                    $end_date = '';
                    $expected_shipment_date = $source['expected_shipment_date'] && $source['expected_shipment_date'] !== '1970-01-01' ? DateHelper::toDisplayDate((string)$source['expected_shipment_date']) : '';
                    $shipment_type = (string)($source['shipment_type'] ?? '');
                    $sales_person = (string)($source['sales_person'] ?? '');
                    $job_reference_no = (string)($source['job_reference_no'] ?? '');
                    $master_awb_no = (string)($source['master_awb_no'] ?? '');
                    $shipper = (string)($source['shipper'] ?? '');
                    $consignee = (string)($source['consignee'] ?? '');
                    $origin = (string)($source['origin'] ?? '');
                    $destination = (string)($source['destination'] ?? '');
                    $no_of_packs = (string)($source['no_of_packs'] ?? '');
                    $gross_weight = (string)($source['gross_weight'] ?? '');
                    $chargeable_weight = (string)($source['chargeable_weight'] ?? '');
                    $volume = (string)($source['volume'] ?? '');
                    $customer_notes = (string)($source['customer_notes'] ?? '');
                    $terms_and_conditions = (string)($source['terms_and_conditions'] ?? '');
                    $grand_subtotal = (string)($source['grand_subtotal'] ?? '');
                    $grand_discount_type = (string)($source['grand_discount_type'] ?? '');
                    $grand_discount_type_value = (string)($source['grand_discount_type_value'] ?? '');
                    $grand_discount_amount = (string)($source['grand_discount_amount'] ?? '');
                    $grand_after_discount = (string)($source['grand_after_discount'] ?? '');
                    $grand_tax = (string)($source['grand_tax'] ?? '');
                    $grand_total = (string)($source['grand_total'] ?? '');

                    $items = $this->db->fetchAll("SELECT * FROM `" . DB::INVOICE_ITEMS . "` WHERE invoice_id = :inv_id AND organization_id = :org_id ORDER BY id ASC", ['inv_id' => $source_invoice_id, 'org_id' => $this->orgId]);
                    $total_rows = count($items);
                    foreach ($items as $item) {
                        $item_id_arr[] = 0;
                        $service_arr[] = (int)$item['service'];
                        $description_arr[] = (string)$item['description'];
                        $qty_arr[] = (float)$item['qty'];
                        $rate_arr[] = (float)$item['rate'];
                        $sub_total_arr[] = (float)$item['sub_total'];
                        $tax_arr[] = (float)$item['tax'];
                        $tax_amount_arr[] = (float)$item['tax_amount'];
                        $total_arr[] = (float)$item['total'];
                    }
                }
            } catch (\Throwable $e) {
                log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                    'module' => 'recurring_invoices',
                    'module_slug' => 'recurring_invoices',
                    'stack_trace' => $e->getTraceAsString(),
                    'error_code' => (string)$e->getCode(),
                ]));
                $error_message = $e->getMessage();
            }
        }

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
                    $invoice_date = DateHelper::toDisplayDate($invoice->invoiceDate) ?: $invoice->invoiceDate;
                    $expiry_date = $invoice->expiryDate !== '1970-01-01' ? DateHelper::toDisplayDate($invoice->expiryDate) : '';
                    $invoice_status = $invoice->invoiceStatus;
                    $reference_no = (string)$invoice->referenceNo;
                    $warehouse_id = (string)$invoice->warehouseId;
                    $profile_name = (string)$invoice->profileName;
                    $frequency = $invoice->frequency;
                    $start_date = DateHelper::toDisplayDate($invoice->startDate) ?: $invoice->startDate;
                    $end_date = $invoice->endDate ? DateHelper::toDisplayDate($invoice->endDate) : '';
                    $expected_shipment_date = $invoice->expectedShipmentDate ? DateHelper::toDisplayDate($invoice->expectedShipmentDate) : '';
                    $payment_term = (string)$invoice->paymentTerm;
                    $shipment_type = (string)$invoice->shipmentType;
                    $sales_person = (string)$invoice->salesPerson;
                    $job_reference_no = (string)$invoice->jobReferenceNo;
                    $master_awb_no = (string)$invoice->masterAwbNo;
                    $shipper = (string)$invoice->shipper;
                    $consignee = (string)$invoice->consignee;
                    $origin = (string)$invoice->origin;
                    $destination = (string)$invoice->destination;
                    $no_of_packs = (string)$invoice->noOfPacks;
                    $gross_weight = (string)$invoice->grossWeight;
                    $chargeable_weight = (string)$invoice->chargeableWeight;
                    $volume = (string)$invoice->volume;
                    $customer_notes = (string)$invoice->customerNotes;
                    $terms_and_conditions = (string)$invoice->termsAndConditions;
                    $grand_subtotal = (string)$invoice->grandSubtotal;
                    $grand_discount_type = $invoice->grandDiscountType;
                    $grand_discount_type_value = (string)$invoice->grandDiscountTypeValue;
                    $grand_discount_amount = (string)$invoice->grandDiscountAmount;
                    $grand_after_discount = (string)$invoice->grandAfterDiscount;
                    $grand_tax = (string)$invoice->grandTax;
                    $grand_total = (string)$invoice->grandTotal;
                    $publish = $invoice->isActive ? 1 : 0;

                    $items = $this->invoiceService->getInvoiceItems($id, $this->orgId);
                    $total_rows = count($items);

                    foreach ($items as $item) {
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
                    $error_message = $e->getMessage();
                    if (function_exists('log_error')) {
                        log_error('Recurring invoice form load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'recurring_invoices', 'module_slug' => 'recurring_invoices', 'error_code' => (string)$e->getCode(), 'stack_trace' => $e->getTraceAsString()]) : ['module' => 'recurring_invoices', 'module_slug' => 'recurring_invoices', 'error_code' => (string)$e->getCode(), 'stack_trace' => $e->getTraceAsString()]);
                    }
                }
            }
        }

        if ($total_rows == 0) {
            $total_rows = 1;
        }

        return Response::html($this->view->render('recurring_invoices/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'customer_id' => $customer_id,
            'invoice_date' => $invoice_date,
            'expiry_date' => $expiry_date,
            'invoice_status' => $invoice_status,
            'reference_no' => $reference_no,
            'warehouse_id' => $warehouse_id,
            'profile_name' => $profile_name,
            'frequency' => $frequency,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'expected_shipment_date' => $expected_shipment_date,
            'payment_term' => $payment_term,
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
            'customer_notes' => $customer_notes,
            'terms_and_conditions' => $terms_and_conditions,
            'grand_subtotal' => $grand_subtotal,
            'grand_discount_type' => $grand_discount_type,
            'grand_discount_type_value' => $grand_discount_type_value,
            'grand_discount_amount' => $grand_discount_amount,
            'grand_after_discount' => $grand_after_discount,
            'grand_tax' => $grand_tax,
            'grand_total' => $grand_total,
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
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
