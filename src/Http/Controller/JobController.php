<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Container;
use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\JobService;
use App\Service\JobItemService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;
use App\Helper\DateHelper;

class JobController extends BaseController
{
    private JobService $jobService;
    private JobItemService $jobItemService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        JobService $jobService,
        JobItemService $jobItemService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->jobService = $jobService;
        $this->jobItemService = $jobItemService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('jobs', 'Job');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $action === 'check_duplicate' => $this->handleCheckDuplicate($request),
            $action === 'ajax_ports' => $this->handleAjaxPorts($request),
            $request->isPost() && $action === 'ajax_add_carrier' => $this->handleAddCarrier($request),

            $request->isPost() && $action === 'update_jobs' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_jobs' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $jobData = $this->buildJobData($request);

        try {
            $this->jobService->updateJob($id, $jobData, $this->orgId, $this->userId);

            $jobItems = $this->buildJobItemsData($request);
            if (!empty($jobItems)) {
                $this->jobItemService->replaceForJob($id, $jobItems, $this->orgId);
            }

            flash_success('The Job has been updated successfully.');
            return Response::redirect('listing_jobs.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            $_SESSION['__jobs_old_input'] = $_POST;
            return Response::redirect("jobs.php?id=$id&action=edit_jobs&error_message=" . urlencode($error));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            $_SESSION['__jobs_old_input'] = $_POST;
            return Response::redirect("jobs.php?id=$id&action=edit_jobs&error_message=" . urlencode($e->getMessage()));
        }
    }

    private function handleCreate(Request $request): Response
    {
        $jobData = $this->buildJobData($request);

        try {
            $newJob = $this->jobService->createJob($jobData, $this->orgId, $this->userId);
            $id = $newJob->id;

            $jobItems = $this->buildJobItemsData($request);
            if (!empty($jobItems)) {
                $this->jobItemService->replaceForJob($id, $jobItems, $this->orgId);
            }

            flash_success('The Job has been saved successfully.');
            return Response::redirect('listing_jobs.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            $_SESSION['__jobs_old_input'] = $_POST;
            return Response::redirect("jobs.php?error_message=" . urlencode($error));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            $_SESSION['__jobs_old_input'] = $_POST;
            return Response::redirect("jobs.php?error_message=" . urlencode($e->getMessage()));
        }
    }

    private function buildJobData(Request $request): array
    {
        $tags = $request->post('tags', []);
        if (is_array($tags)) {
            $tags = implode(', ', array_map('trim', $tags));
        }

        $services = $request->post('services', []);
        if (is_array($services)) {
            $services = implode(', ', array_map('trim', $services));
        }

        return [
            'warehouse_id' => $request->getString('warehouse_id'),
            'customer_id' => $request->getString('customer_id'),
            'quotation_id' => $request->getString('quotation_id'),
            'job_date' => $request->getString('job_date'),
            'job_status' => $request->getString('job_status'),
            'job_seq' => $request->getString('job_seq'),
            'job_no' => $request->getString('job_no'),
            'job_ref_no' => $request->getString('job_ref_no'),
            'sales_person' => $request->getString('sales_person'),
            'sales_person_from_lead' => $request->getString('sales_person_from_lead'),
            'currency' => $request->getString('currency'),
            'exchange_rate' => $request->getString('exchange_rate'),
            'transport_mode' => $this->processArrayField($request->post('transport_mode', [])),
            'shipment_type' => $this->processArrayField($request->post('shipment_type', [])),
            'job_owner' => $request->getString('job_owner'),
            'tags' => $tags,
            'services' => $services,
            'cs_agent' => $request->getString('cs_agent'),
            'incoterm' => $request->getString('incoterm'),
            'email' => $request->getString('email'),
            'supplier_rate' => $request->getString('supplier_rate'),
            'estimated_net_profit' => $request->getString('estimated_net_profit'),
            'estimated_invoice_amount' => $request->getString('estimated_invoice_amount'),
            'etd' => $request->getString('etd'),
            'eta' => $request->getString('eta'),
            'carrier' => $request->getString('carrier'),
            'vessel_name' => $request->getString('vessel_name'),
            'vessel_departure_date' => $request->getString('vessel_departure_date'),
            'flight_no' => $request->getString('flight_no'),
            'flight_departure_date' => $request->getString('flight_departure_date'),
            'job_completion_date' => $request->getString('job_completion_date'),
            'payment_terms' => $request->getString('payment_terms'),
            'hawb' => $request->getString('hawb'),
            'mawb' => $request->getString('mawb'),
            'estimated_cost_amount' => $request->getString('estimated_cost_amount'),
            'declaration_no' => $request->getString('declaration_no'),
            'gross_weight' => $request->getString('gross_weight'),
            'volume_weight' => $request->getString('volume_weight'),
            'chargeable_weight' => $request->getString('chargeable_weight'),
            'no_of_pieces' => $request->getString('no_of_pieces'),
            'commodity_type' => $request->getString('commodity_type'),
            'no_of_containers' => $request->getString('no_of_containers'),
            'insurance_needed' => $request->getString('insurance_needed'),
            'container_type' => $request->getString('container_type'),
            'temperature_control_required' => $request->getString('temperature_control_required'),
            'container_number' => $request->getString('container_number'),
            'special_comments' => $request->getString('special_comments'),
            'landing_country' => $request->getString('landing_country'),
            'landing_port' => $request->getString('landing_port'),
            'loading_place' => $request->getString('loading_place'),
            'billing_city' => $request->getString('billing_city'),
            'billing_state' => $request->getString('billing_state'),
            'billing_code' => $request->getString('billing_code'),
            'billing_country' => $request->getString('billing_country'),
            'destination_country' => $request->getString('destination_country'),
            'destination_port' => $request->getString('destination_port'),
            'fdp' => $request->getString('fdp'),
            'shipping_city' => $request->getString('shipping_city'),
            'shipping_state' => $request->getString('shipping_state'),
            'shipping_code' => $request->getString('shipping_code'),
            'shipping_country' => $request->getString('shipping_country'),
            'subject' => $request->getString('subject'),
            'terms_and_conditions' => $request->getString('terms_and_conditions'),
            'grand_subtotal' => $request->getString('grand_subtotal'),
            'grand_discount_type' => $request->getString('grand_discount_type'),
            'grand_discount_type_value' => $request->getString('grand_discount_type_value'),
            'grand_discount_amount' => $request->getString('grand_discount_amount'),
            'grand_after_discount' => $request->getString('grand_after_discount'),
            'customer_notes' => $request->getString('customer_notes'),
            'grand_tax' => $request->getString('grand_tax'),
            'grand_total' => $request->getString('grand_total'),
            'happy_customer' => $request->getString('happy_customer'),
            'unhappy_reason' => $request->getString('unhappy_reason'),
            'shipment_on_time' => $request->getString('shipment_on_time'),
            'referral' => $request->getString('referral'),
            'notes' => $request->getString('notes'),
            'books_customer_id' => $request->getString('books_customer_id'),
            'quote_id' => $request->getString('quote_id'),
            'project_id' => $request->getString('project_id'),
            'project_created' => $request->getString('project_created'),
            'qrcode' => $request->getString('qrcode'),
        ];
    }

    private function buildJobItemsData(Request $request): array
    {
        $items = [];
        $dimLengths = $request->post('dim_length', []);
        if (!is_array($dimLengths) || empty($dimLengths)) {
            return $items;
        }

        $dimWidths = $request->post('dim_width', []);
        $dimHeights = $request->post('dim_height', []);
        $dimPcs = $request->post('dim_pcs', []);
        $dimVolumes = $request->post('dim_volume', []);
        $dimCbms = $request->post('dim_cbm', []);

        foreach ($dimLengths as $idx => $length) {
            $length = trim((string)$length);
            $width = trim((string)($dimWidths[$idx] ?? ''));
            $height = trim((string)($dimHeights[$idx] ?? ''));
            $pcs = trim((string)($dimPcs[$idx] ?? '1'));
            $volume = trim((string)($dimVolumes[$idx] ?? ''));
            $cbm = trim((string)($dimCbms[$idx] ?? ''));

            if ($length === '' && $width === '' && $height === '') {
                continue;
            }

            $items[] = [
                'dim_length' => $length,
                'dim_width' => $width,
                'dim_height' => $height,
                'dim_pcs' => $pcs !== '' ? $pcs : '1',
                'dim_volume' => $volume !== '' ? $volume : '0',
                'dim_cbm' => $cbm !== '' ? $cbm : '0',
            ];
        }

        return $items;
    }

    private function handleAjaxPorts(Request $request): Response
    {
        $countryId = $request->getInt('country_id');
        if ($countryId <= 0) {
            return Response::json([]);
        }

        try {
            $rows = $this->db->fetchAll(
                "SELECT id, port_name FROM `" . DB::PORTS . "` WHERE country_id = :country_id AND is_active = 1 ORDER BY port_name",
                ['country_id' => $countryId]
            );
            $result = array_map(fn($r) => ['id' => $r['id'], 'port' => $r['port_name']], $rows);
            return Response::json($result);
        } catch (\Throwable $e) {
            return Response::json([]);
        }
    }

    private function showForm(Request $request, int $id): Response
    {
        $module = 'jobs';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $error_message = $request->getString('error_message');
        $action = $request->getString('action');

        // Default values
        $warehouse_id = '0';
        $customer_id = '0';
        $quotation_id = '';
        $job_date = date('d-m-Y');
        $job_status = '';
        $job_seq = '';
        $job_no = '';
        $job_ref_no = '';
        $sales_person = '0';
        $sales_person_from_lead = '';
        $currency = '';
        $exchange_rate = '';
        $transport_mode = '';
        $shipment_type = '';
        $shipment_type_arr = [];
        $job_owner = '0';
        $tags = '';
        $tags_arr = [];
        $services = '';
        $services_arr = [];
        $cs_agent = '0';
        $incoterm = '';
        $email = '';
        $supplier_rate = '';
        $estimated_net_profit = '';
        $estimated_invoice_amount = '';
        $etd = '';
        $eta = '';
        $carrier = '0';
        $vessel_name = '';
        $vessel_departure_date = '';
        $flight_no = '';
        $flight_departure_date = '';
        $job_completion_date = '';
        $payment_terms = '';
        $hawb = '';
        $mawb = '';
        $estimated_cost_amount = '';
        $declaration_no = '';
        $gross_weight = '';
        $volume_weight = '';
        $chargeable_weight = '';
        $no_of_pieces = '';
        $commodity_type = '0';
        $no_of_containers = '';
        $insurance_needed = '0';
        $container_type = '0';
        $temperature_control_required = '0';
        $container_number = '';
        $special_comments = '';
        $landing_country = '0';
        $landing_port = '';
        $loading_place = '';
        $billing_city = '';
        $billing_state = '';
        $billing_code = '';
        $billing_country = '0';
        $destination_country = '0';
        $destination_port = '';
        $fdp = '';
        $shipping_city = '';
        $shipping_state = '';
        $shipping_code = '';
        $shipping_country = '0';
        $subject = '';
        $terms_and_conditions = '';
        $grand_subtotal = '0.00';
        $grand_discount_type = '';
        $grand_discount_type_value = '';
        $grand_discount_amount = '';
        $grand_after_discount = '';
        $customer_notes = '';
        $grand_tax = '0.00';
        $grand_total = '0.00';
        $happy_customer = '';
        $unhappy_reason = '';
        $shipment_on_time = '';
        $referral = '';
        $notes = '';
        $quote_id = '';
        $project_id = '';
        $project_created = '0';
        $qrcode = '';
        $customer_type = '';
        $created_by = '';
        $modified_by = '';
        $books_customer_id = '';
        $approved_time = '';
        $approved_time_resubmission = '';
        $item_dim_id_arr = [];
        $dim_length_arr = [];
        $dim_width_arr = [];
        $dim_height_arr = [];
        $dim_pcs_arr = [];
        $dim_volume_arr = [];
        $dim_cbm_arr = [];
        $total_rows = 1;

        if ($id > 0) {
            try {
                $sql = "SELECT created_by FROM `" . DB::JOBS . "` WHERE id = :id AND organization_id = :org_id";
                $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $this->orgId]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $job = $this->jobService->getJob($id, $this->orgId);

                    $warehouse_id = (string)$job->warehouseId;
                    $customer_id = (string)$job->customerId;
                    $quotation_id = (string)$job->quotationId;
                    $job_date = DateHelper::toDbDate($job->jobDate);
                    $job_date = ($job_date === '1970-01-01' || empty($job_date)) ? date('d-m-Y') : date('d-m-Y', strtotime($job_date));
                    $job_status = $job->jobStatus;
                    $job_seq = (string)$job->jobSeq;
                    $job_no = $job->jobNo;
                    $job_ref_no = $job->jobReferenceNo;
                    $sales_person = (string)$job->salesPerson;
                    $sales_person_from_lead = (string)$job->salesPersonFromLead;
                    $currency = (string)$job->currency;
                    $exchange_rate = (string)$job->exchangeRate;
                    $transport_mode = $job->transportMode;
                    $shipment_type = (string)$job->shipmentType;
                    if (!empty($shipment_type)) {
                        $shipment_type_arr = explode(', ', $shipment_type);
                    }
                    $job_owner = (string)$job->jobOwner;
                    $tags = (string)$job->tags;
                    if (!empty($tags)) {
                        $tags_arr = explode(', ', $tags);
                    }
                    $services = (string)$job->services;
                    if (!empty($services)) {
                        $services_arr = explode(', ', $services);
                    }
                    $cs_agent = (string)$job->csAgent;
                    $incoterm = $job->incoterm;
                    $email = (string)$job->email;
                    $supplier_rate = (string)$job->supplierRate;
                    $estimated_net_profit = (string)$job->estimatedNetProfit;
                    $estimated_invoice_amount = (string)$job->estimatedInvoiceAmount;

                    $etd = $job->etd;
                    $etd = ($etd === '1970-01-01') ? '' : DateHelper::toDisplayDate($etd);

                    $eta = $job->eta;
                    $eta = ($eta === '1970-01-01') ? '' : DateHelper::toDisplayDate($eta);

                    $carrier = (string)$job->carrier;
                    $vessel_name = (string)$job->vesselName;

                    $vessel_departure_date = $job->vesselDepartureDate;
                    $vessel_departure_date = ($vessel_departure_date === '1970-01-01') ? '' : DateHelper::toDisplayDate($vessel_departure_date);

                    $flight_no = (string)$job->flightNo;

                    $flight_departure_date = $job->flightDepartureDate;
                    $flight_departure_date = ($flight_departure_date === '1970-01-01') ? '' : DateHelper::toDisplayDate($flight_departure_date);

                    $job_completion_date = $job->jobCompletionDate;
                    $job_completion_date = ($job_completion_date === '1970-01-01') ? '' : DateHelper::toDisplayDate($job_completion_date);

                    $payment_terms = (string)$job->paymentTerms;
                    $hawb = (string)$job->hawb;
                    $mawb = (string)$job->mawb;
                    $estimated_cost_amount = (string)$job->estimatedCostAmount;
                    $declaration_no = $job->declarationNo;
                    $gross_weight = (string)$job->grossWeight;
                    $volume_weight = (string)$job->volumeWeight;
                    $chargeable_weight = (string)$job->chargeableWeight;
                    $no_of_pieces = (string)$job->noOfPieces;
                    $commodity_type = (string)$job->commodityType;
                    $no_of_containers = (string)$job->noOfContainers;
                    $insurance_needed = $job->insuranceNeeded;
                    $container_type = (string)$job->containerType;
                    $temperature_control_required = $job->temperatureControlRequired;
                    $container_number = (string)$job->containerNumber;
                    $special_comments = (string)$job->specialComments;
                    $landing_country = (string)$job->landingCountry;
                    $landing_port = (string)$job->landingPort;
                    $loading_place = (string)$job->loadingPlace;
                    $billing_city = (string)$job->billingCity;
                    $billing_state = (string)$job->billingState;
                    $billing_code = (string)$job->billingCode;
                    $billing_country = (string)$job->billingCountry;
                    $destination_country = (string)$job->destinationCountry;
                    $destination_port = (string)$job->destinationPort;
                    $fdp = (string)$job->fdp;
                    $shipping_city = (string)$job->shippingCity;
                    $shipping_state = (string)$job->shippingState;
                    $shipping_code = (string)$job->shippingCode;
                    $shipping_country = (string)$job->shippingCountry;
                    $subject = (string)$job->subject;
                    $terms_and_conditions = (string)$job->termsAndConditions;
                    $grand_subtotal = (string)$job->grandSubtotal;
                    $grand_discount_type = $job->grandDiscountType;
                    $grand_discount_type_value = (string)$job->grandDiscountTypeValue;
                    $grand_discount_amount = (string)$job->grandDiscountAmount;
                    $grand_after_discount = (string)$job->grandAfterDiscount;
                    $customer_notes = (string)$job->customerNotes;
                    $grand_tax = (string)$job->grandTax;
                    $grand_total = (string)$job->grandTotal;
                    $happy_customer = $job->happyCustomer;
                    $unhappy_reason = (string)$job->unhappyReason;
                    $shipment_on_time = $job->shipmentOnTime;
                    $referral = (string)$job->referral;
                    $notes = (string)$job->notes;
                    $quote_id = (string)$job->quoteId;
                    $project_id = (string)$job->projectId;
                    $project_created = $job->projectCreated;
                    $qrcode = (string)$job->qrcode;
                    $customer_type = (string)$job->customerType;

                    $created_by = (string)$job->createdBy;
                    $modified_by = (string)$job->modifiedBy;
                    $created_by = $this->resolveUserName((int)$job->createdBy);
                    $modified_by = $this->resolveUserName((int)$job->modifiedBy);
                    $books_customer_id = (string)$job->booksCustomerId;
                    $approved_time = (string)$job->approvedTime;
                    $approved_time_resubmission = (string)$job->approvedTimeResubmission;

                    $dimItems = $this->jobItemService->getByJobId($id, $this->orgId);
                    $item_dim_id_arr = [];
                    $dim_length_arr = [];
                    $dim_width_arr = [];
                    $dim_height_arr = [];
                    $dim_pcs_arr = [];
                    $dim_volume_arr = [];
                    $dim_cbm_arr = [];
                    foreach ($dimItems as $item) {
                        $item_dim_id_arr[] = (string)$item->id;
                        $dim_length_arr[] = $item->dimLength;
                        $dim_width_arr[] = $item->dimWidth;
                        $dim_height_arr[] = $item->dimHeight;
                        $dim_pcs_arr[] = $item->dimPcs;
                        $dim_volume_arr[] = $item->dimVolume;
                        $dim_cbm_arr[] = $item->dimCbm;
                    }
                    $total_rows = count($dimItems);
                } catch (\Throwable $e) {
                    $error_message = $e->getMessage();
                }
            }
        }

        // Restore old input after failed validation
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (isset($_SESSION['__jobs_old_input'])) {
            $old = $_SESSION['__jobs_old_input'];
            unset($_SESSION['__jobs_old_input']);

            foreach ([
                'warehouse_id', 'customer_id', 'quotation_id', 'job_date', 'job_status',
                'job_seq', 'job_no', 'job_ref_no', 'sales_person', 'sales_person_from_lead',
                'currency', 'exchange_rate', 'cs_agent', 'incoterm', 'email',
                'supplier_rate', 'estimated_net_profit', 'estimated_invoice_amount',
                'etd', 'eta', 'carrier', 'vessel_name', 'vessel_departure_date',
                'flight_no', 'flight_departure_date', 'job_completion_date',
                'payment_terms', 'hawb', 'mawb', 'estimated_cost_amount', 'declaration_no',
                'gross_weight', 'volume_weight', 'chargeable_weight', 'no_of_pieces',
                'commodity_type', 'no_of_containers', 'insurance_needed', 'container_type',
                'temperature_control_required', 'container_number', 'special_comments',
                'landing_country', 'landing_port', 'loading_place',
                'billing_city', 'billing_state', 'billing_code', 'billing_country',
                'destination_country', 'destination_port', 'fdp',
                'shipping_city', 'shipping_state', 'shipping_code', 'shipping_country',
                'subject', 'terms_and_conditions',
                'grand_subtotal', 'grand_discount_type', 'grand_discount_type_value',
                'grand_discount_amount', 'grand_after_discount',
                'customer_notes', 'grand_tax', 'grand_total',
                'happy_customer', 'unhappy_reason', 'shipment_on_time', 'referral',
                'notes', 'quote_id', 'project_id', 'books_customer_id',
            ] as $key) {
                if (isset($old[$key])) {
                    $$key = is_array($old[$key]) ? implode(', ', $old[$key]) : (string)$old[$key];
                }
            }

            if (isset($old['tags'])) {
                $tags = is_array($old['tags']) ? implode(', ', $old['tags']) : $old['tags'];
                $tags_arr = is_array($old['tags']) ? $old['tags'] : explode(', ', $tags);
            }
            if (isset($old['services'])) {
                $services = is_array($old['services']) ? implode(', ', $old['services']) : $old['services'];
                $services_arr = is_array($old['services']) ? $old['services'] : explode(', ', $services);
            }
            if (isset($old['shipment_type'])) {
                $shipment_type = is_array($old['shipment_type']) ? implode(', ', $old['shipment_type']) : $old['shipment_type'];
                $shipment_type_arr = is_array($old['shipment_type']) ? $old['shipment_type'] : explode(', ', $shipment_type);
            }
            if (isset($old['transport_mode'])) {
                $transport_mode = is_array($old['transport_mode']) ? implode(', ', $old['transport_mode']) : $old['transport_mode'];
            }

            if (isset($old['dim_length']) && is_array($old['dim_length'])) {
                $dim_length_arr = $old['dim_length'];
                $dim_width_arr = $old['dim_width'] ?? [];
                $dim_height_arr = $old['dim_height'] ?? [];
                $dim_pcs_arr = $old['dim_pcs'] ?? [];
                $dim_volume_arr = $old['dim_volume'] ?? [];
                $dim_cbm_arr = $old['dim_cbm'] ?? [];
                $item_dim_id_arr = $old['item_dim_id'] ?? [];
                $total_rows = count($dim_length_arr);
            }
        }

        // Fetch dropdown data
        try {
            $warehousesList = $this->db->fetchAll("SELECT id, warehouse_name FROM `" . DB::ORGANIZATIONS . "` WHERE is_active=1");
        } catch (\Throwable $e) {
            $warehousesList = [];
        }
        try {
            $customersList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE is_active=1 AND approved=1 ORDER BY id DESC");
        } catch (\Throwable $e) {
            $customersList = [];
        }
        try {
            $usersList = $this->db->fetchAll("SELECT id, full_name FROM `" . DB::USERS . "` WHERE is_active=1 ORDER BY full_name");
        } catch (\Throwable $e) {
            $usersList = [];
        }
        try {
            $currenciesList = $this->db->fetchAll("SELECT id, currency FROM `" . DB::CURRENCIES . "` WHERE is_active=1 ORDER BY id ASC");
        } catch (\Throwable $e) {
            $currenciesList = [];
        }
        try {
            $jobStatusesList = $this->db->fetchAll("SELECT id, job_status FROM `" . DB::JOB_STATUSES . "` WHERE is_active=1 ORDER BY job_status");
        } catch (\Throwable $e) {
            $jobStatusesList = [];
        }
        try {
            $incotermsList = $this->db->fetchAll("SELECT id, incoterm FROM `" . DB::INCOTERMS . "` ORDER BY incoterm ASC");
        } catch (\Throwable $e) {
            $incotermsList = [];
        }
        try {
            $carriersList = $this->db->fetchAll("SELECT id, carrier_name FROM `" . DB::CARRIERS . "` ORDER BY carrier_name ASC");
        } catch (\Throwable $e) {
            $carriersList = [];
        }
        try {
            $containerTypesList = $this->db->fetchAll("SELECT id, container_type FROM `" . DB::CONTAINER_TYPES . "` WHERE is_active=1 ORDER BY container_type");
        } catch (\Throwable $e) {
            $containerTypesList = [];
        }
        try {
            $tagsList = $this->db->fetchAll("SELECT id, value FROM `" . DB::TAXONOMIES . "` WHERE is_active=1 AND type='job_tag' ORDER BY value");
        } catch (\Throwable $e) {
            $tagsList = [];
        }
        try {
            $servicesList = $this->db->fetchAll("SELECT id, item_name FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' ORDER BY item_name");
        } catch (\Throwable $e) {
            $servicesList = [];
        }
        try {
            $countriesList = $this->db->fetchAll("SELECT id, country FROM `" . DB::GEO_COUNTRIES . "` WHERE is_active=1 ORDER BY country");
        } catch (\Throwable $e) {
            $countriesList = [];
        }
        try {
            $quotesList = $this->db->fetchAll("SELECT id, quotation_no FROM `" . DB::QUOTATIONS . "` WHERE organization_id = :org_id ORDER BY id DESC", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $quotesList = [];
        }

        return Response::html($this->view->render('jobs/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'warehouse_id' => $warehouse_id,
            'customer_id' => $customer_id,
            'quotation_id' => $quotation_id,
            'job_date' => $job_date,
            'job_status' => $job_status,
            'job_seq' => $job_seq,
            'job_no' => $job_no,
            'job_ref_no' => $job_ref_no,
            'sales_person' => $sales_person,
            'sales_person_from_lead' => $sales_person_from_lead,
            'currency' => $currency,
            'exchange_rate' => $exchange_rate,
            'transport_mode' => $transport_mode,
            'shipment_type' => $shipment_type,
            'shipment_type_arr' => $shipment_type_arr,
            'job_owner' => $job_owner,
            'tags' => $tags,
            'tags_arr' => $tags_arr,
            'services' => $services,
            'services_arr' => $services_arr,
            'cs_agent' => $cs_agent,
            'incoterm' => $incoterm,
            'email' => $email,
            'supplier_rate' => $supplier_rate,
            'estimated_net_profit' => $estimated_net_profit,
            'estimated_invoice_amount' => $estimated_invoice_amount,
            'etd' => $etd,
            'eta' => $eta,
            'carrier' => $carrier,
            'vessel_name' => $vessel_name,
            'vessel_departure_date' => $vessel_departure_date,
            'flight_no' => $flight_no,
            'flight_departure_date' => $flight_departure_date,
            'job_completion_date' => $job_completion_date,
            'payment_terms' => $payment_terms,
            'hawb' => $hawb,
            'mawb' => $mawb,
            'estimated_cost_amount' => $estimated_cost_amount,
            'declaration_no' => $declaration_no,
            'gross_weight' => $gross_weight,
            'volume_weight' => $volume_weight,
            'chargeable_weight' => $chargeable_weight,
            'no_of_pieces' => $no_of_pieces,
            'commodity_type' => $commodity_type,
            'no_of_containers' => $no_of_containers,
            'insurance_needed' => $insurance_needed,
            'container_type' => $container_type,
            'temperature_control_required' => $temperature_control_required,
            'container_number' => $container_number,
            'special_comments' => $special_comments,
            'landing_country' => $landing_country,
            'landing_port' => $landing_port,
            'loading_place' => $loading_place,
            'billing_city' => $billing_city,
            'billing_state' => $billing_state,
            'billing_code' => $billing_code,
            'billing_country' => $billing_country,
            'destination_country' => $destination_country,
            'destination_port' => $destination_port,
            'fdp' => $fdp,
            'shipping_city' => $shipping_city,
            'shipping_state' => $shipping_state,
            'shipping_code' => $shipping_code,
            'shipping_country' => $shipping_country,
            'subject' => $subject,
            'terms_and_conditions' => $terms_and_conditions,
            'grand_subtotal' => $grand_subtotal,
            'grand_discount_type' => $grand_discount_type,
            'grand_discount_type_value' => $grand_discount_type_value,
            'grand_discount_amount' => $grand_discount_amount,
            'grand_after_discount' => $grand_after_discount,
            'customer_notes' => $customer_notes,
            'grand_tax' => $grand_tax,
            'grand_total' => $grand_total,
            'happy_customer' => $happy_customer,
            'unhappy_reason' => $unhappy_reason,
            'shipment_on_time' => $shipment_on_time,
            'referral' => $referral,
            'notes' => $notes,
            'quote_id' => $quote_id,
            'project_id' => $project_id,
            'project_created' => $project_created,
            'qrcode' => $qrcode,
            'customer_type' => $customer_type,
            'created_by' => $created_by,
            'modified_by' => $modified_by,
            'books_customer_id' => $books_customer_id,
            'approved_time' => $approved_time,
            'approved_time_resubmission' => $approved_time_resubmission,
            'item_dim_id_arr' => $item_dim_id_arr,
            'dim_length_arr' => $dim_length_arr,
            'dim_width_arr' => $dim_width_arr,
            'dim_height_arr' => $dim_height_arr,
            'dim_pcs_arr' => $dim_pcs_arr,
            'dim_volume_arr' => $dim_volume_arr,
            'dim_cbm_arr' => $dim_cbm_arr,
            'total_rows' => $total_rows,
            'warehousesList' => $warehousesList,
            'customersList' => $customersList,
            'usersList' => $usersList,
            'currenciesList' => $currenciesList,
            'jobStatusesList' => $jobStatusesList,
            'incotermsList' => $incotermsList,
            'carriersList' => $carriersList,
            'containerTypesList' => $containerTypesList,
            'tagsList' => $tagsList,
            'servicesList' => $servicesList,
            'countriesList' => $countriesList,
            'quotesList' => $quotesList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }

    private function handleCheckDuplicate(Request $request): Response
    {
        $field = $request->getString('field');
        $value = $request->getString('value');
        $excludeId = $request->getInt('id');

        if (empty($field) || empty($value)) {
            return Response::json(['duplicate' => false]);
        }

        $allowedFields = ['job_no', 'job_ref_no'];
        if (!in_array($field, $allowedFields, true)) {
            return Response::json(['duplicate' => false]);
        }

        $sql = "SELECT COUNT(*) as cnt FROM `" . DB::JOBS . "`
                WHERE $field = :value AND organization_id = :org_id";
        $params = ['value' => $value, 'org_id' => $this->orgId];

        if ($excludeId > 0) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $row = $this->db->fetchOne($sql, $params);
        $isDuplicate = ($row && (int)$row['cnt'] > 0);

        return Response::json(['duplicate' => $isDuplicate]);
    }

    private function resolveUserName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        try {
            $row = $this->db->fetchOne(
                "SELECT email FROM `" . DB::USERS . "` WHERE id = :id",
                ['id' => $userId]
            );
            return $row ? ($row['email'] ?? (string)$userId) : (string)$userId;
        } catch (\Throwable $e) {
            return (string)$userId;
        }
    }

    private function handleAddCarrier(Request $request): Response
    {
        $name = trim($request->getString('carrier_name'));
        if (empty($name)) {
            return Response::json(['success' => false, 'message' => 'Carrier name is required.']);
        }

        try {
            $existing = $this->db->fetchOne(
                "SELECT id FROM `" . DB::CARRIERS . "` WHERE carrier_name = :name",
                ['name' => $name]
            );
            if ($existing) {
                return Response::json(['success' => true, 'id' => (int)$existing['id'], 'carrier_name' => $name]);
            }

            $newId = $this->db->insert(
                "INSERT INTO `" . DB::CARRIERS . "` (carrier_name, organization_id) VALUES (:carrier_name, :organization_id)",
                ['carrier_name' => $name, 'organization_id' => $this->orgId]
            );
            return Response::json(['success' => true, 'id' => $newId, 'carrier_name' => $name]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    private function processArrayField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            $trimmed = array_map('trim', $value);
            $filtered = array_filter($trimmed, fn($v) => $v !== '');
            return !empty($filtered) ? implode(', ', $filtered) : null;
        }
        $v = trim((string)$value);
        return $v !== '' ? $v : null;
    }
}
