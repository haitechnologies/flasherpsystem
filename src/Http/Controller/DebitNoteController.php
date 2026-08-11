<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\DebitNoteService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class DebitNoteController extends BaseController
{
    private DebitNoteService $debitNoteService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        DebitNoteService $debitNoteService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->debitNoteService = $debitNoteService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('debit_notes', 'Debit Note');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_debit_notes' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_debit_notes' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $noteData = $this->buildNoteData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->debitNoteService->updateNote($id, $noteData, $itemsData, $this->orgId, $this->userId);

            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=debit_notes&id=$id");
            }
            flash_success('The Debit Note has been updated successfully.');
            return Response::redirect('listing_debit_notes.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("debit_notes.php?id=$id&action=edit_debit_notes");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'debit_notes',
                'module_slug' => 'debit_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("debit_notes.php?id=$id&action=edit_debit_notes");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $noteData = $this->buildNoteData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $newNote = $this->debitNoteService->createNote($noteData, $itemsData, $this->orgId, $this->userId);
            $id = $newNote->id;

            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=debit_notes&id=$id");
            }
            flash_success('The Debit Note has been saved successfully.');
            return Response::redirect('listing_debit_notes.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("debit_notes.php");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'debit_notes',
                'module_slug' => 'debit_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("debit_notes.php");
        }
    }

    private function buildNoteData(Request $request): array
    {
        return [
            'vendor_id' => $request->getString('vendor_id'),
            'debit_note_date' => $request->getString('debit_note_date'),
            'debit_note_status' => $request->getString('debit_note_status', 'draft'),
            'reference_no' => $request->getString('reference_no'),
            'purchase_id' => $request->getString('purchase_id', '0'),
            'warehouse_id' => $request->getString('warehouse_id'),
            'purchase_person' => $request->getString('purchase_person'),
            'vendor_notes' => $request->getString('vendor_notes'),
            'terms_and_conditions' => $request->getString('terms_and_conditions'),
            'grand_subtotal' => $request->getString('grand_subtotal', '0.00'),
            'grand_discount_type' => $request->getString('grand_discount_type'),
            'grand_discount_type_value' => $request->getString('grand_discount_type_value'),
            'grand_discount_amount' => $request->getString('grand_discount_amount'),
            'grand_after_discount' => $request->getString('grand_after_discount'),
            'grand_tax' => $request->getString('grand_tax', '0.00'),
            'grand_total' => $request->getString('grand_total', '0.00'),
            'publish' => $request->get('publish') ? true : false,
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
        $module = 'debit_notes';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $error_message = '';
        $action = $request->getString('action');

        $debit_note_no = '';
        $debit_note_date = date('d-m-Y');
        $debit_note_status = 'draft';
        $reference_no = '';
        $vendor_id = '0';
        $purchase_id = '0';
        $warehouse_id = '0';
        $purchase_person = '0';
        $vendor_notes = '';
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
        $sub_total_arr = [];
        $tax_arr = [];
        $tax_amount_arr = [];
        $total_arr = [];
        $total_rows = 1;

        if ($id > 0) {
            $created_by = 0;
            try {
                $sql = "SELECT created_by FROM `" . DB::DEBIT_NOTES . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $debitNote = $this->debitNoteService->getDebitNote($id, $this->orgId);
                    $debit_note_no = $debitNote->debitNoteNo;
                    $debit_note_status = $debitNote->debitNoteStatus;
                    $debit_note_date = DateHelper::toDisplayDate($debitNote->debitNoteDate) ?: $debitNote->debitNoteDate;
                    $reference_no = (string)$debitNote->referenceNo;
                    $vendor_id = (string)$debitNote->vendorId;
                    $purchase_id = (string)$debitNote->purchaseId;
                    $warehouse_id = (string)$debitNote->warehouseId;
                    $purchase_person = (string)$debitNote->purchasePerson;
                    $vendor_notes = (string)$debitNote->vendorNotes;
                    $terms_and_conditions = (string)$debitNote->termsAndConditions;
                    $grand_subtotal = (string)$debitNote->grandSubtotal;
                    $grand_discount_type = (string)$debitNote->grandDiscountType;
                    $grand_discount_type_value = (string)$debitNote->grandDiscountTypeValue;
                    $grand_discount_amount = (string)$debitNote->grandDiscountAmount;
                    $grand_after_discount = (string)$debitNote->grandAfterDiscount;
                    $grand_tax = (string)$debitNote->grandTax;
                    $grand_total = (string)$debitNote->grandTotal;
                    $is_active = $debitNote->isActive ? 1 : 0;

                    $items = $this->debitNoteService->getDebitNoteItems($id);
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
                    log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                        'module' => 'debit_notes',
                        'module_slug' => 'debit_notes',
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

        $vendorsList = [];
        $warehousesList = [];
        $itemsList = [];
        try {
            $vendorsList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::VENDORS . "` WHERE is_active=1 AND organization_id=:org_id ORDER BY id DESC", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
        }
        try {
            $warehousesList = $this->db->fetchAll("SELECT id, warehouse_name FROM `" . DB::WAREHOUSES . "` WHERE is_active=1 ORDER BY warehouse_name");
        } catch (\Throwable $e) {
        }
        try {
            $itemsList = $this->db->fetchAll("SELECT id, item_name FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' AND organization_id=:org_id ORDER BY item_name", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
        }

        return Response::html($this->view->render('debit_notes/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'debit_note_no' => $debit_note_no,
            'debit_note_date' => $debit_note_date,
            'debit_note_status' => $debit_note_status,
            'reference_no' => $reference_no,
            'vendor_id' => $vendor_id,
            'purchase_id' => $purchase_id,
            'warehouse_id' => $warehouse_id,
            'purchase_person' => $purchase_person,
            'vendor_notes' => $vendor_notes,
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
            'sub_total_arr' => $sub_total_arr,
            'tax_arr' => $tax_arr,
            'tax_amount_arr' => $tax_amount_arr,
            'total_arr' => $total_arr,
            'vendorsList' => $vendorsList,
            'warehousesList' => $warehousesList,
            'itemsList' => $itemsList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
