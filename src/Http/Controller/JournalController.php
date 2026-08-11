<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\JournalService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class JournalController extends BaseController
{
    private JournalService $journalService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        JournalService $journalService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->journalService = $journalService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('journals', 'Journal');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_journals' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_journals' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $journalData = $this->buildJournalData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->journalService->updateJournal($id, $journalData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Journal has been updated successfully.');
            return Response::redirect('listing_journals.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("journals.php?id=$id&action=edit_journals");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'journals',
                'module_slug' => 'journals',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("journals.php?id=$id&action=edit_journals");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $journalData = $this->buildJournalData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->journalService->createJournal($journalData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Journal has been saved successfully.');
            return Response::redirect('listing_journals.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("journals.php");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'journals',
                'module_slug' => 'journals',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("journals.php");
        }
    }

    private function buildJournalData(Request $request): array
    {
        return [
            'journal_date' => $request->getString('journal_date'),
            'journal_no' => $request->getString('journal_no'),
            'reference_no' => $request->getString('reference_no'),
            'notes' => $request->getString('notes'),
            'reporting_method' => $request->getString('reporting_method'),
            'currency' => $request->getString('currency'),
            'journal_status' => $request->getString('journal_status', 'draft'),
            'publish' => $request->get('publish') ? 1 : 0,
            'warehouse_id' => $request->getString('warehouse_id'),
        ];
    }

    private function buildItemsData(Request $request): array
    {
        $totalRows = (int)$request->getString('total_rows', '1');
        $items = [];

        for ($i = 0; $i < $totalRows; $i++) {
            $itemId = $request->getArrayItem('item_id', $i);
            $account = $request->getArrayItem('account', $i);
            $debit = $request->getArrayItem('debit', $i, '0');
            $credit = $request->getArrayItem('credit', $i, '0');

            if (empty($account) || (int)$account <= 0) {
                continue;
            }

            $items[] = [
                'item_id' => !empty($itemId) ? (int)$itemId : null,
                'account' => (int)$account,
                'description' => $request->getArrayItem('description', $i),
                'debit' => (float)$debit,
                'credit' => (float)$credit,
            ];
        }

        return $items;
    }

    private function showForm(Request $request, int $id): Response
    {
        $module = 'journals';
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

        $journal_status = '';
        $journal_date = date('d-m-Y');
        $journal_no = 'Auto-generated';
        $reference_no = '';
        $notes = '';
        $reporting_method = 'accrual_cash';
        $currency = 'AED';
        $grand_subtotal = '';
        $grand_total = '';
        $publish = 0;

        $item_id_arr = [];
        $account_arr = [];
        $description_arr = [];
        $debit_arr = [];
        $credit_arr = [];
        $total_rows = 1;

        if ($id > 0) {
            $created_by = 0;
            try {
                $sql = "SELECT created_by FROM `" . DB::JOURNALS . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $journal = $this->journalService->getJournal($id, $this->orgId);
                    $journal_status = $journal->journalStatus;
                    $journal_date = DateHelper::toDisplayDate($journal->journalDate) ?: $journal->journalDate;
                    $journal_no = $journal->journalNo;
                    $reference_no = (string)$journal->referenceNo;
                    $notes = $journal->notes;
                    $reporting_method = $journal->reportingMethod;
                    $currency = $journal->currency;
                    $grand_subtotal = (string)$journal->grandSubtotal;
                    $grand_total = (string)$journal->grandTotal;
                    $publish = $journal->isActive ? 1 : 0;

                    $items = $this->journalService->getJournalItems($id, $this->orgId);
                    $total_rows = count($items);

                    foreach ($items as $item) {
                        $item_id_arr[] = $item->id;
                        $account_arr[] = $item->account;
                        $description_arr[] = $item->description;
                        $debit_arr[] = $item->debit;
                        $credit_arr[] = $item->credit;
                    }
                } catch (\Throwable $e) {
                    log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                        'module' => 'journals',
                        'module_slug' => 'journals',
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

        return Response::html($this->view->render('journals/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'journal_status' => $journal_status,
            'journal_date' => $journal_date,
            'journal_no' => $journal_no,
            'reference_no' => $reference_no,
            'notes' => $notes,
            'reporting_method' => $reporting_method,
            'currency' => $currency,
            'grand_subtotal' => $grand_subtotal,
            'grand_total' => $grand_total,
            'publish' => $publish,
            'total_rows' => $total_rows,
            'item_id_arr' => $item_id_arr,
            'account_arr' => $account_arr,
            'description_arr' => $description_arr,
            'debit_arr' => $debit_arr,
            'credit_arr' => $credit_arr,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
