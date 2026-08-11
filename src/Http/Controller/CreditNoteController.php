<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\CreditNoteService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class CreditNoteController extends BaseController
{
    private CreditNoteService $creditNoteService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        CreditNoteService $creditNoteService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->creditNoteService = $creditNoteService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('credit_notes', 'Credit Note');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_credit_notes' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_credit_notes' && $this->canCreate()
                => $this->handleCreate($request),
            $request->isPost() && $action === 'clone_credit_notes' && $id > 0 && $this->canCreate()
                => $this->handleClone($request, $id),
            $request->isPost() && $action === 'convert_credit_notes' && $id > 0 && $this->canEdit()
                => $this->handleConvert($request, $id),
            $request->isPost() && $action === 'void_credit_notes' && $id > 0 && $this->canEdit()
                => $this->handleVoid($request, $id),
            $request->isPost() && $action === 'open_credit_notes' && $id > 0 && $this->canEdit()
                => $this->handleOpen($request, $id),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $noteData = $this->buildNoteData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->creditNoteService->updateNote($id, $noteData, $itemsData, $this->orgId, $this->userId);
            updateCustomerLogs((int)$noteData['customer_id'], 'credit_note', 'edit', $id);

            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=credit_notes&id=$id");
            }
            flash_success('The Credit Note has been updated successfully.');
            return Response::redirect('listing_credit_notes.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("credit_notes.php?id=$id&action=edit_credit_notes");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'credit_notes',
                'module_slug' => 'credit_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("credit_notes.php?id=$id&action=edit_credit_notes");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $noteData = $this->buildNoteData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $newNote = $this->creditNoteService->createNote($noteData, $itemsData, $this->orgId, $this->userId);
            $id = $newNote->id;
            updateCustomerLogs((int)$noteData['customer_id'], 'credit_note', 'add', (int)$id);

            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=credit_notes&id=$id");
            }
            flash_success('The Credit Note has been saved successfully.');
            return Response::redirect('listing_credit_notes.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("credit_notes.php");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'credit_notes',
                'module_slug' => 'credit_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("credit_notes.php");
        }
    }

    private function handleClone(Request $request, int $id): Response
    {
        try {
            $cloned = $this->creditNoteService->cloneNote($id, $this->orgId, $this->userId);
            flash_success('The Credit Note has been cloned successfully.');
            return Response::redirect('credit_note_overview.php?credit_note_id=' . $cloned->id);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'credit_notes',
                'module_slug' => 'credit_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect('credit_note_overview.php?credit_note_id=' . $id);
        }
    }

    private function handleConvert(Request $request, int $id): Response
    {
        try {
            $invoiceId = $this->creditNoteService->convertToInvoice($id, $this->orgId, $this->userId);
            flash_success('Credit Note converted to invoice successfully.');
            return Response::redirect('invoice_overview.php?invoice_id=' . $invoiceId);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'credit_notes',
                'module_slug' => 'credit_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect('credit_note_overview.php?credit_note_id=' . $id);
        }
    }

    private function handleVoid(Request $request, int $id): Response
    {
        try {
            $this->creditNoteService->voidNote($id, $this->orgId, $this->userId);
            flash_success('Credit Note voided with a reversing journal entry.');
            return Response::redirect('credit_note_overview.php?credit_note_id=' . $id);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'credit_notes',
                'module_slug' => 'credit_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect('credit_note_overview.php?credit_note_id=' . $id);
        }
    }

    private function handleOpen(Request $request, int $id): Response
    {
        try {
            $this->creditNoteService->openNote($id, $this->orgId, $this->userId);
            flash_success('Credit Note opened and journal entry posted.');
            return Response::redirect('credit_note_overview.php?credit_note_id=' . $id);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'credit_notes',
                'module_slug' => 'credit_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect('credit_note_overview.php?credit_note_id=' . $id);
        }
    }

    private function buildNoteData(Request $request): array
    {
        return [
            'customer_id' => $request->getString('customer_id'),
            'credit_note_date' => $request->getString('credit_note_date'),
            'credit_note_status' => $request->getString('credit_note_status', 'draft'),
            'reference_no' => $request->getString('reference_no'),
            'invoice_id' => $request->getString('invoice_id', '0'),
            'warehouse_id' => $request->getString('warehouse_id'),
            'subject' => $request->getString('subject'),
            'payment_term' => $request->getString('payment_term'),
            'expiry_date' => $request->getString('expiry_date'),
            'expected_shipment_date' => $request->getString('expected_shipment_date'),
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
            'grand_subtotal' => $request->getString('grand_subtotal', '0.00'),
            'grand_discount_type' => $request->getString('grand_discount_type'),
            'grand_discount_type_value' => $request->getString('grand_discount_type_value'),
            'grand_discount_amount' => $request->getString('grand_discount_amount'),
            'grand_after_discount' => $request->getString('grand_after_discount'),
            'grand_tax' => $request->getString('grand_tax', '0.00'),
            'grand_total' => $request->getString('grand_total', '0.00'),
            'publish' => $request->get('publish') ? true : false,
            'is_active' => $request->get('publish') ? true : false,
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
                'discount_type' => $request->getArrayItem('discount_type', $i),
                'discount_type_value' => $request->getArrayItem('discount_type_value', $i, '0'),
                'discount_amount' => $request->getArrayItem('discount_amount', $i, '0'),
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
        $module = 'credit_notes';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $error_message = '';
        $action = $request->getString('action');

        $credit_note_no = '';
        $credit_note_date = date('d-m-Y');
        $credit_note_status = 'draft';
        $reference_no = '';
        $customer_id = '0';
        $invoice_id = '0';
        $warehouse_id = '0';
        $subject = '';
        $payment_term = '0';
        $expiry_date = '';
        $expected_shipment_date = '';
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
        $customer_notes = '';
        $terms_and_conditions = '';
        $grand_subtotal = '0.00';
        $grand_discount_type = '';
        $grand_discount_type_value = '';
        $grand_discount_amount = '';
        $grand_after_discount = '';
        $grand_tax = '0.00';
        $grand_total = '0.00';
        $is_active = 1;

        $item_id_arr = [];
        $service_arr = [];
        $description_arr = [];
        $qty_arr = [];
        $rate_arr = [];
        $discount_type_arr = [];
        $discount_type_value_arr = [];
        $discount_amount_arr = [];
        $sub_total_arr = [];
        $tax_arr = [];
        $tax_amount_arr = [];
        $total_arr = [];
        $total_rows = 1;

        if ($id > 0) {
            $created_by = 0;
            try {
                $sql = "SELECT created_by FROM `" . DB::CREDIT_NOTES . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $creditNote = $this->creditNoteService->getCreditNote($id, $this->orgId);
                    $credit_note_no = $creditNote->creditNoteNo;
                    $credit_note_status = $creditNote->creditNoteStatus;
                    $credit_note_date = DateHelper::toDisplayDate($creditNote->creditNoteDate) ?: $creditNote->creditNoteDate;
                    $reference_no = (string)$creditNote->referenceNo;
                    $customer_id = (string)$creditNote->customerId;
                    $invoice_id = (string)$creditNote->invoiceId;
                    $warehouse_id = (string)$creditNote->warehouseId;
                    $subject = (string)$creditNote->subject;
                    $payment_term = (string)$creditNote->paymentTerm;
                    $expiry_date = DateHelper::toDisplayDate((string)$creditNote->expiryDate) ?: (string)$creditNote->expiryDate;
                    $expected_shipment_date = DateHelper::toDisplayDate((string)$creditNote->expectedShipmentDate) ?: (string)$creditNote->expectedShipmentDate;
                    $shipment_type = (string)$creditNote->shipmentType;
                    $sales_person = (string)$creditNote->salesPerson;
                    $job_reference_no = (string)$creditNote->jobReferenceNo;
                    $master_awb_no = (string)$creditNote->masterAwbNo;
                    $shipper = (string)$creditNote->shipper;
                    $consignee = (string)$creditNote->consignee;
                    $origin = (string)$creditNote->origin;
                    $destination = (string)$creditNote->destination;
                    $no_of_packs = (string)$creditNote->noOfPacks;
                    $gross_weight = (string)$creditNote->grossWeight;
                    $chargeable_weight = (string)$creditNote->chargeableWeight;
                    $volume = (string)$creditNote->volume;
                    $customer_notes = (string)$creditNote->customerNotes;
                    $terms_and_conditions = (string)$creditNote->termsAndConditions;
                    $grand_subtotal = (string)$creditNote->grandSubtotal;
                    $grand_discount_type = (string)$creditNote->grandDiscountType;
                    $grand_discount_type_value = (string)$creditNote->grandDiscountTypeValue;
                    $grand_discount_amount = (string)$creditNote->grandDiscountAmount;
                    $grand_after_discount = (string)$creditNote->grandAfterDiscount;
                    $grand_tax = (string)$creditNote->grandTax;
                    $grand_total = (string)$creditNote->grandTotal;
                    $is_active = $creditNote->isActive ? 1 : 0;

                    $items = $this->creditNoteService->getCreditNoteItems($id, $this->orgId);
                    $total_rows = count($items);

                    foreach ($items as $item) {
                        $item_id_arr[] = $item->id;
                        $service_arr[] = $item->service;
                        $description_arr[] = $item->description;
                        $qty_arr[] = $item->qty;
                        $rate_arr[] = $item->rate;
                        $discount_type_arr[] = $item->discountType;
                        $discount_type_value_arr[] = $item->discountTypeValue;
                        $discount_amount_arr[] = $item->discountAmount;
                        $sub_total_arr[] = $item->subTotal;
                        $tax_arr[] = $item->tax;
                        $tax_amount_arr[] = $item->taxAmount;
                        $total_arr[] = $item->total;
                    }
                } catch (\Throwable $e) {
                    log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                        'module' => 'credit_notes',
                        'module_slug' => 'credit_notes',
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
        $itemsList = [];
        try {
            $customersList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE is_active=1 AND approved=1 AND organization_id=:org_id ORDER BY id DESC", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            if (function_exists('log_error')) {
                                log_error('Credit note form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]) : ['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $warehousesList = $this->db->fetchAll("SELECT id, warehouse_name FROM `" . DB::WAREHOUSES . "` WHERE is_active=1 ORDER BY warehouse_name");
        } catch (\Throwable $e) {
            if (function_exists('log_error')) {
                                log_error('Credit note form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]) : ['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $itemsList = $this->db->fetchAll("SELECT id, item_name FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' ORDER BY item_name");
        } catch (\Throwable $e) {
            if (function_exists('log_error')) {
                                log_error('Credit note form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]) : ['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]);
            }
        }

        $paymentTermsList = [];
        $shippersList = [];
        $consigneesList = [];
        $countriesList = [];
        $portsList = [];
        $invoiceList = [];
        if ((int)$customer_id > 0) {
            try {
                $invoiceList = $this->db->fetchAll(
                    "SELECT i.id, i.invoice_no, i.invoice_date, i.grand_total,
                            (i.grand_total - IFNULL((SELECT SUM(pri.amount_received) FROM `" . DB::PAYMENT_RECEIVED_ITEMS . "` pri INNER JOIN `" . DB::PAYMENTS_RECEIVED . "` pr ON pr.id = pri.payment_id WHERE pri.invoice_id = i.id AND pr.payment_status <> 'void'), 0)) AS balance_due
                     FROM `" . DB::INVOICES . "` i
                     WHERE i.customer_id = :customer_id AND i.organization_id = :org_id AND i.recurring = 0
                     ORDER BY i.id DESC",
                    ['customer_id' => (int)$customer_id, 'org_id' => $this->orgId]
                );
            } catch (\Throwable $e) {
                $invoiceList = [];
                if (function_exists('log_error')) {
                    log_error('credit_notes form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]) : ['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]);
                }
            }
        }
        try {
            $paymentTermsList = $this->db->fetchAll("SELECT id, payment_term FROM `" . DB::PAYMENT_TERMS . "` WHERE is_active=1 ORDER BY payment_term");
        } catch (\Throwable $e) {
            if (function_exists('log_error')) {
                                log_error('Credit note form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]) : ['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $shippersList = $this->db->fetchAll("SELECT id, shipper_name FROM `" . DB::SHIPPERS . "` WHERE is_active=1 AND organization_id=:org_id ORDER BY shipper_name", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            if (function_exists('log_error')) {
                                log_error('Credit note form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]) : ['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $consigneesList = $this->db->fetchAll("SELECT id, consignee_name FROM `" . DB::CONSIGNEES . "` WHERE is_active=1 AND organization_id=:org_id ORDER BY consignee_name", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            if (function_exists('log_error')) {
                                log_error('Credit note form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]) : ['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $countriesList = $this->db->fetchAll("SELECT id, country, abbr FROM `" . DB::GEO_COUNTRIES . "` WHERE is_active=1 ORDER BY country");
        } catch (\Throwable $e) {
            if (function_exists('log_error')) {
                                log_error('Credit note form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]) : ['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $portsList = $this->db->fetchAll("SELECT id, port_name, port_code FROM `" . DB::PORTS . "` WHERE is_active=1 ORDER BY port_name");
        } catch (\Throwable $e) {
            if (function_exists('log_error')) {
                                log_error('Credit note form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]) : ['module' => 'credit_notes', 'module_slug' => 'credit_notes', 'error_code' => (string)$e->getCode()]);
            }
        }

        return Response::html($this->view->render('credit_notes/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'credit_note_no' => $credit_note_no,
            'credit_note_date' => $credit_note_date,
            'credit_note_status' => $credit_note_status,
            'reference_no' => $reference_no,
            'customer_id' => $customer_id,
            'invoice_id' => $invoice_id,
            'warehouse_id' => $warehouse_id,
            'subject' => $subject,
            'payment_term' => $payment_term,
            'expiry_date' => $expiry_date,
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
            'customer_notes' => $customer_notes,
            'terms_and_conditions' => $terms_and_conditions,
            'grand_subtotal' => $grand_subtotal,
            'grand_discount_type' => $grand_discount_type,
            'grand_discount_type_value' => $grand_discount_type_value,
            'grand_discount_amount' => $grand_discount_amount,
            'grand_after_discount' => $grand_after_discount,
            'grand_tax' => $grand_tax,
            'grand_total' => $grand_total,
            'is_active' => $is_active,
            'total_rows' => $total_rows,
            'item_id_arr' => $item_id_arr,
            'service_arr' => $service_arr,
            'description_arr' => $description_arr,
            'qty_arr' => $qty_arr,
            'rate_arr' => $rate_arr,
            'discount_type_arr' => $discount_type_arr,
            'discount_type_value_arr' => $discount_type_value_arr,
            'discount_amount_arr' => $discount_amount_arr,
            'sub_total_arr' => $sub_total_arr,
            'tax_arr' => $tax_arr,
            'tax_amount_arr' => $tax_amount_arr,
            'total_arr' => $total_arr,
            'customersList' => $customersList,
            'warehousesList' => $warehousesList,
            'itemsList' => $itemsList,
            'paymentTermsList' => $paymentTermsList,
            'shippersList' => $shippersList,
            'consigneesList' => $consigneesList,
            'countriesList' => $countriesList,
            'portsList' => $portsList,
            'invoiceList' => $invoiceList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
