<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Container;
use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\JobService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;
use App\Helper\DateHelper;

class JobController extends BaseController
{
    private JobService $jobService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        JobService $jobService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->jobService = $jobService;
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
            flash_success('The Job has been updated successfully.');
            return Response::redirect('listing_jobs.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("jobs.php?id=$id&action=edit_jobs&error_message=" . urlencode($error));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            return Response::redirect("jobs.php?id=$id&action=edit_jobs&error_message=" . urlencode($e->getMessage()));
        }
    }

    private function handleCreate(Request $request): Response
    {
        $jobData = $this->buildJobData($request);

        try {
            $newJob = $this->jobService->createJob($jobData, $this->orgId, $this->userId);
            $id = $newJob->id;
            flash_success('The Job has been saved successfully.');
            return Response::redirect('listing_jobs.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("jobs.php?error_message=" . urlencode($error));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
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
            'currency' => $request->getString('currency'),
            'exchange_rate' => $request->getString('exchange_rate'),
            'transport_mode' => $request->getString('transport_mode'),
            'shipment_type' => $request->getString('shipment_type'),
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
            'quote_id' => $request->getString('quote_id'),
            'project_id' => $request->getString('project_id'),
            'publish' => $request->get('publish') ? true : false,
        ];
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
        $currency = '';
        $exchange_rate = '';
        $transport_mode = '';
        $shipment_type = '';
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
        $landing_port = '0';
        $loading_place = '0';
        $billing_city = '';
        $billing_state = '';
        $billing_code = '';
        $billing_country = '0';
        $destination_country = '0';
        $destination_port = '0';
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
        $is_active = 1;
        $customer_type = '';
        $created_by = 0;

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
                    $currency = (string)$job->currency;
                    $exchange_rate = (string)$job->exchangeRate;
                    $transport_mode = $job->transportMode;
                    $shipment_type = $job->shipmentType;
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
                    $is_active = $job->isActive ? 1 : 0;
                    $customer_type = (string)$job->customerType;
                } catch (\Throwable $e) {
                    $error_message = $e->getMessage();
                }
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
            $commodityTypesList = $this->db->fetchAll("SELECT id, commodity_type FROM `" . DB::COMMODITY_TYPES . "` WHERE is_active=1 ORDER BY commodity_type");
        } catch (\Throwable $e) {
            $commodityTypesList = [];
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
            'currency' => $currency,
            'exchange_rate' => $exchange_rate,
            'transport_mode' => $transport_mode,
            'shipment_type' => $shipment_type,
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
            'is_active' => $is_active,
            'customer_type' => $customer_type,
            'warehousesList' => $warehousesList,
            'customersList' => $customersList,
            'usersList' => $usersList,
            'currenciesList' => $currenciesList,
            'jobStatusesList' => $jobStatusesList,
            'incotermsList' => $incotermsList,
            'carriersList' => $carriersList,
            'commodityTypesList' => $commodityTypesList,
            'containerTypesList' => $containerTypesList,
            'tagsList' => $tagsList,
            'servicesList' => $servicesList,
            'countriesList' => $countriesList,
            'quotesList' => $quotesList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
