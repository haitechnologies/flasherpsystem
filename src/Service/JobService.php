<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Job;
use App\Repository\JobRepository;
use App\Repository\CustomerRepository;
use App\Core\DB;
use App\Core\Database;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

/**
 * Job Service
 *
 * Implements business logic and validations for jobs.
 */
class JobService
{
    private JobRepository $jobRepo;
    private CustomerRepository $customerRepo;
    private Database $db;

    public function __construct(JobRepository $jobRepo, CustomerRepository $customerRepo, Database $db)
    {
        $this->jobRepo = $jobRepo;
        $this->customerRepo = $customerRepo;
        $this->db = $db;
    }

    /**
     * Get Job by ID and organization
     *
     * @throws NotFoundException
     */
    public function getJob(int $id, int $orgId): Job
    {
        $job = $this->jobRepo->find($id, $orgId);
        if ($job === null) {
            throw new NotFoundException("Job with ID {$id} not found.");
        }
        return $job;
    }

    /**
     * Create a new job
     *
     * @throws ValidationException
     */
    public function createJob(array $data, int $orgId, int $userId): Job
    {
        $this->validateJobData($data, $orgId);

        $this->db->beginTransaction();
        try {
            $jobNo = !empty($data['job_no']) ? trim((string)$data['job_no']) : null;
            if ($jobNo === null) {
                $prefix = 'FL-JB' . date('ym');
                $lastJobNo = $this->jobRepo->getLastJobNoForMonth($prefix, $orgId);
                if ($lastJobNo !== null) {
                    $lastSerial = (int) substr($lastJobNo, -4);
                    $newSerial = $lastSerial + 1;
                } else {
                    $newSerial = 1;
                }
                $jobNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);
            }

            $jobDate = $this->parseDate($data['job_date'] ?? date('Y-m-d'));
            $etd = $this->parseOptionalDate($data['etd'] ?? '');
            $eta = $this->parseOptionalDate($data['eta'] ?? '');
            $vesselDepartureDate = $this->parseOptionalDate($data['vessel_departure_date'] ?? '');
            $flightDepartureDate = $this->parseOptionalDate($data['flight_departure_date'] ?? '');
            $jobCompletionDate = $this->parseOptionalDate($data['job_completion_date'] ?? '');

            $tags = $this->processTags($data['tags'] ?? null);
            $services = $this->processServices($data['services'] ?? null);
            $shipmentType = $this->processShipmentType($data['shipment_type'] ?? null);

            $job = new Job(
                id: null,
                organizationId: $orgId,
                jobDate: $jobDate,
                jobStatus: !empty($data['job_status']) ? trim((string)$data['job_status']) : 'draft',
                warehouseId: !empty($data['warehouse_id']) ? (int)$data['warehouse_id'] : 0,
                customerId: !empty($data['customer_id']) ? (int)$data['customer_id'] : 0,
                quotationId: !empty($data['quotation_id']) ? (int)$data['quotation_id'] : null,
                jobReferenceNo: !empty($data['job_ref_no']) ? trim((string)$data['job_ref_no']) : '',
                jobNo: $jobNo,
                jobSeq: !empty($data['job_seq']) ? (int)$data['job_seq'] : 0,
                salesPerson: !empty($data['sales_person']) ? (int)$data['sales_person'] : 0,
                salesPersonFromLead: !empty($data['sales_person_from_lead']) ? (int)$data['sales_person_from_lead'] : 0,
                currency: !empty($data['currency']) ? trim((string)$data['currency']) : '0',
                exchangeRate: !empty($data['exchange_rate']) ? (float)$data['exchange_rate'] : 0.0,
                transportMode: !empty($data['transport_mode']) ? trim((string)$data['transport_mode']) : '0',
                shipmentType: $shipmentType,
                jobOwner: !empty($data['job_owner']) ? (int)$data['job_owner'] : 0,
                tags: $tags,
                services: $services,
                csAgent: !empty($data['cs_agent']) ? (int)$data['cs_agent'] : 0,
                incoterm: !empty($data['incoterm']) ? trim((string)$data['incoterm']) : '0',
                email: !empty($data['email']) ? trim((string)$data['email']) : null,
                supplierRate: !empty($data['supplier_rate']) ? (float)$data['supplier_rate'] : 0.0,
                estimatedNetProfit: !empty($data['estimated_net_profit']) ? (float)$data['estimated_net_profit'] : 0.0,
                estimatedInvoiceAmount: !empty($data['estimated_invoice_amount']) ? (float)$data['estimated_invoice_amount'] : 0.0,
                etd: $etd,
                eta: $eta,
                carrier: !empty($data['carrier']) ? (int)$data['carrier'] : 0,
                vesselName: !empty($data['vessel_name']) ? trim((string)$data['vessel_name']) : null,
                vesselDepartureDate: $vesselDepartureDate,
                flightNo: !empty($data['flight_no']) ? trim((string)$data['flight_no']) : null,
                flightDepartureDate: $flightDepartureDate,
                jobCompletionDate: $jobCompletionDate,
                paymentTerms: !empty($data['payment_terms']) ? trim((string)$data['payment_terms']) : null,
                hawb: !empty($data['hawb']) ? trim((string)$data['hawb']) : null,
                mawb: !empty($data['mawb']) ? trim((string)$data['mawb']) : null,
                estimatedCostAmount: !empty($data['estimated_cost_amount']) ? (float)$data['estimated_cost_amount'] : 0.0,
                declarationNo: !empty($data['declaration_no']) ? trim((string)$data['declaration_no']) : '',
                grossWeight: !empty($data['gross_weight']) ? (float)$data['gross_weight'] : 0.0,
                volumeWeight: !empty($data['volume_weight']) ? (float)$data['volume_weight'] : 0.0,
                cbm: !empty($data['cbm']) ? (float)$data['cbm'] : 0.0,
                chargeableWeight: !empty($data['chargeable_weight']) ? (float)$data['chargeable_weight'] : 0.0,
                noOfPieces: !empty($data['no_of_pieces']) ? (int)$data['no_of_pieces'] : 0,
                commodityType: !empty($data['commodity_type']) ? trim((string)$data['commodity_type']) : null,
                noOfContainers: !empty($data['no_of_containers']) ? (int)$data['no_of_containers'] : 0,
                insuranceNeeded: !empty($data['insurance_needed']) ? trim((string)$data['insurance_needed']) : '0',
                containerType: !empty($data['container_type']) ? (int)$data['container_type'] : 0,
                temperatureControlRequired: !empty($data['temperature_control_required']) ? trim((string)$data['temperature_control_required']) : '0',
                containerNumber: !empty($data['container_number']) ? trim((string)$data['container_number']) : null,
                specialComments: !empty($data['special_comments']) ? trim((string)$data['special_comments']) : null,
                landingCountry: !empty($data['landing_country']) ? (int)$data['landing_country'] : 0,
                landingPort: !empty($data['landing_port']) ? (int)$data['landing_port'] : 0,
                loadingPlace: !empty($data['loading_place']) ? trim((string)$data['loading_place']) : null,
                billingCity: !empty($data['billing_city']) ? trim((string)$data['billing_city']) : null,
                billingState: !empty($data['billing_state']) ? trim((string)$data['billing_state']) : null,
                billingCode: !empty($data['billing_code']) ? trim((string)$data['billing_code']) : null,
                billingCountry: !empty($data['billing_country']) ? (int)$data['billing_country'] : 0,
                destinationCountry: !empty($data['destination_country']) ? (int)$data['destination_country'] : 0,
                destinationPort: !empty($data['destination_port']) ? (int)$data['destination_port'] : 0,
                fdp: !empty($data['fdp']) ? trim((string)$data['fdp']) : null,
                shippingCity: !empty($data['shipping_city']) ? trim((string)$data['shipping_city']) : null,
                shippingState: !empty($data['shipping_state']) ? trim((string)$data['shipping_state']) : null,
                shippingCode: !empty($data['shipping_code']) ? trim((string)$data['shipping_code']) : null,
                shippingCountry: !empty($data['shipping_country']) ? (int)$data['shipping_country'] : 0,
                subject: !empty($data['subject']) ? trim((string)$data['subject']) : null,
                termsAndConditions: !empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null,
                grandSubtotal: !empty($data['grand_subtotal']) ? (float)$data['grand_subtotal'] : 0.0,
                grandDiscountType: !empty($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : '0.00',
                grandDiscountTypeValue: !empty($data['grand_discount_type_value']) ? (float)$data['grand_discount_type_value'] : 0.0,
                grandDiscountAmount: !empty($data['grand_discount_amount']) ? (float)$data['grand_discount_amount'] : 0.0,
                grandAfterDiscount: !empty($data['grand_after_discount']) ? (float)$data['grand_after_discount'] : 0.0,
                customerNotes: !empty($data['customer_notes']) ? trim((string)$data['customer_notes']) : null,
                grandTax: !empty($data['grand_tax']) ? (float)$data['grand_tax'] : 0.0,
                grandTotal: !empty($data['grand_total']) ? (float)$data['grand_total'] : 0.0,
                happyCustomer: !empty($data['happy_customer']) ? trim((string)$data['happy_customer']) : '',
                unhappyReason: !empty($data['unhappy_reason']) ? trim((string)$data['unhappy_reason']) : null,
                shipmentOnTime: !empty($data['shipment_on_time']) ? trim((string)$data['shipment_on_time']) : '',
                referral: !empty($data['referral']) ? trim((string)$data['referral']) : null,
                notes: !empty($data['notes']) ? trim((string)$data['notes']) : null,
                booksCustomerId: !empty($data['customer_id']) ? (int)$data['customer_id'] : 0,
                quoteId: !empty($data['quote_id']) ? (int)$data['quote_id'] : 0,
                projectId: !empty($data['project_id']) ? (int)$data['project_id'] : 0,
                projectCreated: !empty($data['project_created']) ? trim((string)$data['project_created']) : '0',
                qrcode: !empty($data['qrcode']) ? trim((string)$data['qrcode']) : null,
                modifiedBy: null,
                customerType: null,
                approvedTime: null,
                approvedTimeResubmission: null,
                publish: true,
                isActive: true,
                createdAt: null,
                updatedAt: null,
                updatedBy: null,
                createdBy: $userId,
                pdf: null
            );

            $savedJob = $this->jobRepo->save($job);

            $this->db->commit();

            return $savedJob;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing job
     *
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updateJob(int $id, array $data, int $orgId, int $userId): Job
    {
        $job = $this->getJob($id, $orgId);
        $this->validateJobData($data, $orgId);

        $this->db->beginTransaction();
        try {
            $jobDate = isset($data['job_date']) ? $this->parseDate((string)$data['job_date']) : $job->jobDate;
            $etd = isset($data['etd']) ? $this->parseOptionalDate((string)$data['etd']) : $job->etd;
            $eta = isset($data['eta']) ? $this->parseOptionalDate((string)$data['eta']) : $job->eta;
            $vesselDepartureDate = isset($data['vessel_departure_date']) ? $this->parseOptionalDate((string)$data['vessel_departure_date']) : $job->vesselDepartureDate;
            $flightDepartureDate = isset($data['flight_departure_date']) ? $this->parseOptionalDate((string)$data['flight_departure_date']) : $job->flightDepartureDate;
            $jobCompletionDate = isset($data['job_completion_date']) ? $this->parseOptionalDate((string)$data['job_completion_date']) : $job->jobCompletionDate;

            $tags = isset($data['tags']) ? $this->processTags($data['tags']) : $job->tags;
            $services = isset($data['services']) ? $this->processServices($data['services']) : $job->services;
            $shipmentType = isset($data['shipment_type']) ? $this->processShipmentType($data['shipment_type']) : $job->shipmentType;

            $updatedJob = new Job(
                id: $job->id,
                organizationId: $job->organizationId,
                jobDate: $jobDate,
                jobStatus: isset($data['job_status']) ? trim((string)$data['job_status']) : $job->jobStatus,
                warehouseId: isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : $job->warehouseId,
                customerId: isset($data['customer_id']) ? (int)$data['customer_id'] : $job->customerId,
                quotationId: isset($data['quotation_id']) ? (int)$data['quotation_id'] : $job->quotationId,
                jobReferenceNo: isset($data['job_ref_no']) ? trim((string)$data['job_ref_no']) : $job->jobReferenceNo,
                jobNo: isset($data['job_no']) ? trim((string)$data['job_no']) : $job->jobNo,
                jobSeq: isset($data['job_seq']) ? (int)$data['job_seq'] : $job->jobSeq,
                salesPerson: isset($data['sales_person']) ? (int)$data['sales_person'] : $job->salesPerson,
                salesPersonFromLead: isset($data['sales_person_from_lead']) ? (int)$data['sales_person_from_lead'] : $job->salesPersonFromLead,
                currency: isset($data['currency']) ? trim((string)$data['currency']) : $job->currency,
                exchangeRate: isset($data['exchange_rate']) ? (float)$data['exchange_rate'] : $job->exchangeRate,
                transportMode: isset($data['transport_mode']) ? trim((string)$data['transport_mode']) : $job->transportMode,
                shipmentType: $shipmentType,
                jobOwner: isset($data['job_owner']) ? (int)$data['job_owner'] : $job->jobOwner,
                tags: $tags,
                services: $services,
                csAgent: isset($data['cs_agent']) ? (int)$data['cs_agent'] : $job->csAgent,
                incoterm: isset($data['incoterm']) ? trim((string)$data['incoterm']) : $job->incoterm,
                email: isset($data['email']) ? (empty($data['email']) ? null : trim((string)$data['email'])) : $job->email,
                supplierRate: isset($data['supplier_rate']) ? (float)$data['supplier_rate'] : $job->supplierRate,
                estimatedNetProfit: isset($data['estimated_net_profit']) ? (float)$data['estimated_net_profit'] : $job->estimatedNetProfit,
                estimatedInvoiceAmount: isset($data['estimated_invoice_amount']) ? (float)$data['estimated_invoice_amount'] : $job->estimatedInvoiceAmount,
                etd: $etd,
                eta: $eta,
                carrier: isset($data['carrier']) ? (int)$data['carrier'] : $job->carrier,
                vesselName: isset($data['vessel_name']) ? (empty($data['vessel_name']) ? null : trim((string)$data['vessel_name'])) : $job->vesselName,
                vesselDepartureDate: $vesselDepartureDate,
                flightNo: isset($data['flight_no']) ? (empty($data['flight_no']) ? null : trim((string)$data['flight_no'])) : $job->flightNo,
                flightDepartureDate: $flightDepartureDate,
                jobCompletionDate: $jobCompletionDate,
                paymentTerms: isset($data['payment_terms']) ? (empty($data['payment_terms']) ? null : trim((string)$data['payment_terms'])) : $job->paymentTerms,
                hawb: isset($data['hawb']) ? (empty($data['hawb']) ? null : trim((string)$data['hawb'])) : $job->hawb,
                mawb: isset($data['mawb']) ? (empty($data['mawb']) ? null : trim((string)$data['mawb'])) : $job->mawb,
                estimatedCostAmount: isset($data['estimated_cost_amount']) ? (float)$data['estimated_cost_amount'] : $job->estimatedCostAmount,
                declarationNo: isset($data['declaration_no']) ? trim((string)$data['declaration_no']) : $job->declarationNo,
                grossWeight: isset($data['gross_weight']) ? (float)$data['gross_weight'] : $job->grossWeight,
                volumeWeight: isset($data['volume_weight']) ? (float)$data['volume_weight'] : $job->volumeWeight,
                cbm: isset($data['cbm']) ? (float)$data['cbm'] : $job->cbm,
                chargeableWeight: isset($data['chargeable_weight']) ? (float)$data['chargeable_weight'] : $job->chargeableWeight,
                noOfPieces: isset($data['no_of_pieces']) ? (int)$data['no_of_pieces'] : $job->noOfPieces,
                commodityType: isset($data['commodity_type']) ? trim((string)$data['commodity_type']) : $job->commodityType,
                noOfContainers: isset($data['no_of_containers']) ? (int)$data['no_of_containers'] : $job->noOfContainers,
                insuranceNeeded: isset($data['insurance_needed']) ? trim((string)$data['insurance_needed']) : $job->insuranceNeeded,
                containerType: isset($data['container_type']) ? (int)$data['container_type'] : $job->containerType,
                temperatureControlRequired: isset($data['temperature_control_required']) ? trim((string)$data['temperature_control_required']) : $job->temperatureControlRequired,
                containerNumber: isset($data['container_number']) ? (empty($data['container_number']) ? null : trim((string)$data['container_number'])) : $job->containerNumber,
                specialComments: isset($data['special_comments']) ? (empty($data['special_comments']) ? null : trim((string)$data['special_comments'])) : $job->specialComments,
                landingCountry: isset($data['landing_country']) ? (int)$data['landing_country'] : $job->landingCountry,
                landingPort: isset($data['landing_port']) ? (int)$data['landing_port'] : $job->landingPort,
                loadingPlace: isset($data['loading_place']) ? (empty($data['loading_place']) ? null : trim((string)$data['loading_place'])) : $job->loadingPlace,
                billingCity: isset($data['billing_city']) ? (empty($data['billing_city']) ? null : trim((string)$data['billing_city'])) : $job->billingCity,
                billingState: isset($data['billing_state']) ? (empty($data['billing_state']) ? null : trim((string)$data['billing_state'])) : $job->billingState,
                billingCode: isset($data['billing_code']) ? (empty($data['billing_code']) ? null : trim((string)$data['billing_code'])) : $job->billingCode,
                billingCountry: isset($data['billing_country']) ? (int)$data['billing_country'] : $job->billingCountry,
                destinationCountry: isset($data['destination_country']) ? (int)$data['destination_country'] : $job->destinationCountry,
                destinationPort: isset($data['destination_port']) ? (int)$data['destination_port'] : $job->destinationPort,
                fdp: isset($data['fdp']) ? (empty($data['fdp']) ? null : trim((string)$data['fdp'])) : $job->fdp,
                shippingCity: isset($data['shipping_city']) ? (empty($data['shipping_city']) ? null : trim((string)$data['shipping_city'])) : $job->shippingCity,
                shippingState: isset($data['shipping_state']) ? (empty($data['shipping_state']) ? null : trim((string)$data['shipping_state'])) : $job->shippingState,
                shippingCode: isset($data['shipping_code']) ? (empty($data['shipping_code']) ? null : trim((string)$data['shipping_code'])) : $job->shippingCode,
                shippingCountry: isset($data['shipping_country']) ? (int)$data['shipping_country'] : $job->shippingCountry,
                subject: isset($data['subject']) ? (empty($data['subject']) ? null : trim((string)$data['subject'])) : $job->subject,
                termsAndConditions: isset($data['terms_and_conditions']) ? (empty($data['terms_and_conditions']) ? null : trim((string)$data['terms_and_conditions'])) : $job->termsAndConditions,
                grandSubtotal: isset($data['grand_subtotal']) ? (float)$data['grand_subtotal'] : $job->grandSubtotal,
                grandDiscountType: isset($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : $job->grandDiscountType,
                grandDiscountTypeValue: isset($data['grand_discount_type_value']) ? (float)$data['grand_discount_type_value'] : $job->grandDiscountTypeValue,
                grandDiscountAmount: isset($data['grand_discount_amount']) ? (float)$data['grand_discount_amount'] : $job->grandDiscountAmount,
                grandAfterDiscount: isset($data['grand_after_discount']) ? (float)$data['grand_after_discount'] : $job->grandAfterDiscount,
                customerNotes: isset($data['customer_notes']) ? (empty($data['customer_notes']) ? null : trim((string)$data['customer_notes'])) : $job->customerNotes,
                grandTax: isset($data['grand_tax']) ? (float)$data['grand_tax'] : $job->grandTax,
                grandTotal: isset($data['grand_total']) ? (float)$data['grand_total'] : $job->grandTotal,
                happyCustomer: isset($data['happy_customer']) ? trim((string)$data['happy_customer']) : $job->happyCustomer,
                unhappyReason: isset($data['unhappy_reason']) ? (empty($data['unhappy_reason']) ? null : trim((string)$data['unhappy_reason'])) : $job->unhappyReason,
                shipmentOnTime: isset($data['shipment_on_time']) ? trim((string)$data['shipment_on_time']) : $job->shipmentOnTime,
                referral: isset($data['referral']) ? (empty($data['referral']) ? null : trim((string)$data['referral'])) : $job->referral,
                notes: isset($data['notes']) ? (empty($data['notes']) ? null : trim((string)$data['notes'])) : $job->notes,
                booksCustomerId: isset($data['customer_id']) ? (int)$data['customer_id'] : $job->booksCustomerId,
                quoteId: isset($data['quote_id']) ? (int)$data['quote_id'] : $job->quoteId,
                projectId: isset($data['project_id']) ? (int)$data['project_id'] : $job->projectId,
                projectCreated: isset($data['project_created']) ? (empty($data['project_created']) ? '0' : trim((string)$data['project_created'])) : $job->projectCreated,
                qrcode: isset($data['qrcode']) ? (empty($data['qrcode']) ? null : trim((string)$data['qrcode'])) : $job->qrcode,
                modifiedBy: $job->modifiedBy,
                customerType: $job->customerType,
                approvedTime: $job->approvedTime,
                approvedTimeResubmission: $job->approvedTimeResubmission,
                publish: $job->publish,
                isActive: $job->isActive,
                createdAt: $job->createdAt,
                updatedAt: $job->updatedAt,
                updatedBy: $userId,
                createdBy: $job->createdBy,
                pdf: $job->pdf
            );

            $savedJob = $this->jobRepo->save($updatedJob);

            $this->db->commit();

            return $savedJob;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * List all jobs in an organization
     */
    public function list(int $orgId): array
    {
        return $this->jobRepo->findAll($orgId);
    }

    /**
     * Delete a job
     */
    public function deleteJob(int $id, int $orgId): bool
    {
        $job = $this->getJob($id, $orgId);

        $this->db->beginTransaction();
        try {
            $result = $this->jobRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update status of a job
     */
    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        return $this->jobRepo->updateStatus($id, $status, $orgId);
    }

    /**
     * Send job for approval (Operations -> Accounts review)
     */
    public function sendForApproval(int $id, int $orgId): void
    {
        $statusId = $this->getStatusId('pending_approval');
        if ($statusId === null) {
            throw new \RuntimeException("'pending_approval' status not found. Run migration first.");
        }

        $this->db->execute(
            "UPDATE `" . DB::JOBS . "` SET job_status = :status, approved_time_resubmission = NOW(), updated_at = NOW() WHERE id = :id AND organization_id = :org_id",
            ['status' => (string)$statusId, 'id' => $id, 'org_id' => $orgId]
        );
    }

    /**
     * Approve a job (Accounts)
     */
    public function approveJob(int $id, int $orgId): void
    {
        $statusId = $this->getStatusId('approved');
        if ($statusId === null) {
            throw new \RuntimeException("'approved' status not found.");
        }

        $this->db->execute(
            "UPDATE `" . DB::JOBS . "` SET job_status = :status, approved_time = NOW(), updated_at = NOW() WHERE id = :id AND organization_id = :org_id",
            ['status' => (string)$statusId, 'id' => $id, 'org_id' => $orgId]
        );
    }

    /**
     * Reject a job back to draft (Accounts)
     */
    public function rejectJob(int $id, int $orgId): void
    {
        $statusId = $this->getStatusId('draft');
        if ($statusId === null) {
            throw new \RuntimeException("'draft' status not found.");
        }

        $this->db->execute(
            "UPDATE `" . DB::JOBS . "` SET job_status = :status, updated_at = NOW() WHERE id = :id AND organization_id = :org_id",
            ['status' => (string)$statusId, 'id' => $id, 'org_id' => $orgId]
        );
    }

    /**
     * Check if a job is in pending_approval status
     */
    public function isPendingApproval(int $jobStatusValue): bool
    {
        $pendingId = $this->getStatusId('pending_approval');
        return $pendingId !== null && $jobStatusValue === $pendingId;
    }

    /**
     * Get status ID by name from erp_job_statuses
     */
    private function getStatusId(string $statusName): ?int
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM `" . DB::JOB_STATUSES . "` WHERE LOWER(job_status) = LOWER(:status) AND is_active = 1 LIMIT 1",
            ['status' => $statusName]
        );
        return $row ? (int)$row['id'] : null;
    }

    /**
     * Get draft status ID
     */
    public function getDraftStatusId(): ?int
    {
        return $this->getStatusId('draft');
    }

    /**
     * Get approved status ID
     */
    public function getApprovedStatusId(): ?int
    {
        return $this->getStatusId('approved');
    }

    /**
     * Get pending_approval status ID
     */
    public function getPendingApprovalStatusId(): ?int
    {
        return $this->getStatusId('pending_approval');
    }

    /**
     * Validate Job fields
     *
     * @throws ValidationException
     */
    private function validateJobData(array $data, int $orgId): void
    {
        if (empty($data['warehouse_id']) || $data['warehouse_id'] === 'Please select') {
            throw new ValidationException(['warehouse_id' => 'Please select warehouse.']);
        }
        if (empty($data['customer_id']) || $data['customer_id'] === 'Please select' || $data['customer_id'] === '0') {
            throw new ValidationException(['customer_id' => 'Please select Customer.']);
        }
        if (empty($data['job_owner']) || $data['job_owner'] === 'Please select' || $data['job_owner'] === '0') {
            throw new ValidationException(['job_owner' => 'Please select Job Owner.']);
        }
        if (empty($data['job_ref_no'])) {
            throw new ValidationException(['job_ref_no' => 'Job Ref No is mandatory.']);
        }
        if (empty($data['job_no'])) {
            throw new ValidationException(['job_no' => 'Job No is mandatory.']);
        }
        if (empty($data['declaration_no'])) {
            throw new ValidationException(['declaration_no' => 'Customs Declaration No is mandatory.']);
        }

        // Check duplicate job_ref_no
        if (!empty($data['job_ref_no'])) {
            $existing = $this->jobRepo->findByField('job_ref_no', trim((string)$data['job_ref_no']), $orgId, (int)($data['id'] ?? 0));
            if ($existing !== null) {
                throw new ValidationException(['job_ref_no' => 'Job Ref No already exists.']);
            }
        }

        // Check duplicate job_no
        if (!empty($data['job_no'])) {
            $existing = $this->jobRepo->findByField('job_no', trim((string)$data['job_no']), $orgId, (int)($data['id'] ?? 0));
            if ($existing !== null) {
                throw new ValidationException(['job_no' => 'Job No already exists.']);
            }
        }

        // Verify customer exists if provided
        if (!empty($data['customer_id']) && $data['customer_id'] !== 'Please select' && $data['customer_id'] !== '0') {
            $customerId = (int)$data['customer_id'];
            $customer = $this->customerRepo->find($customerId, $orgId);
            if ($customer === null) {
                throw new ValidationException(['customer_id' => 'Selected customer does not exist in your organization.']);
            }
        }
    }

    /**
     * Parse a date string for DB storage
     */
    private function parseDate(string $dateStr): string
    {
        if (empty($dateStr)) {
            return date('Y-m-d');
        }
        $dt = \DateTime::createFromFormat('d-m-Y', $dateStr);
        if ($dt) {
            return $dt->format('Y-m-d');
        }
        if (strpos($dateStr, '-') === false) {
            return \App\Helper\DateHelper::toDisplayDate($dateStr) ?: $dateStr;
        }
        return $dateStr;
    }

    /**
     * Parse an optional date (empty = 1970-01-01)
     */
    private function parseOptionalDate(string $dateStr): string
    {
        if (empty($dateStr)) {
            return '1970-01-01';
        }
        $dt = \DateTime::createFromFormat('d-m-Y', $dateStr);
        if ($dt) {
            return $dt->format('Y-m-d');
        }
        if (strpos($dateStr, '-') === false) {
            return \App\Helper\DateHelper::toDisplayDate($dateStr) ?: $dateStr;
        }
        return $dateStr;
    }

    /**
     * Process tags input (array or comma-separated string)
     */
    private function processTags(mixed $tags): ?string
    {
        if ($tags === null) {
            return null;
        }
        if (is_array($tags)) {
            return implode(', ', array_map('trim', $tags));
        }
        if (is_string($tags) && $tags !== '') {
            return trim($tags);
        }
        return null;
    }

    /**
     * Process services input (array or comma-separated string)
     */
    private function processServices(mixed $services): ?string
    {
        if ($services === null) {
            return null;
        }
        if (is_array($services)) {
            return implode(', ', array_map('trim', $services));
        }
        if (is_string($services) && $services !== '') {
            return trim($services);
        }
        return null;
    }

    private const VALID_SHIPMENT_TYPES = ['export', 'import', 'transit', 'local_jobs', 'import_export'];

    private function processShipmentType(mixed $shipmentType): ?string
    {
        if ($shipmentType === null) {
            return null;
        }
        if (is_array($shipmentType)) {
            $trimmed = array_map('trim', $shipmentType);
            $filtered = array_filter($trimmed, fn($v) => $v !== '' && in_array($v, self::VALID_SHIPMENT_TYPES, true));
            return !empty($filtered) ? implode(', ', $filtered) : null;
        }
        if (is_string($shipmentType) && $shipmentType !== '') {
            $v = trim($shipmentType);
            return in_array($v, self::VALID_SHIPMENT_TYPES, true) ? $v : null;
        }
        return null;
    }
}
