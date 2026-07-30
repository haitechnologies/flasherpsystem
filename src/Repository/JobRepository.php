<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Job;

/**
 * Job Repository
 *
 * Handles PDO-based data access for erp_jobs table with strict tenant isolation.
 */
class JobRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find job by ID and organization
     */
    public function find(int $id, int $orgId): ?Job
    {
        $sql = "SELECT id, organization_id, job_date, job_status, warehouse_id, customer_id, quotation_id, job_ref_no, job_no, job_seq, sales_person, sales_person_from_lead, project_created, qrcode, currency, exchange_rate, transport_mode, shipment_type, job_owner, tags, services, cs_agent, incoterm, email, supplier_rate, estimated_net_profit, estimated_invoice_amount, etd, eta, carrier, vessel_name, vessel_departure_date, flight_no, flight_departure_date, job_completion_date, payment_terms, hawb, mawb, estimated_cost_amount, declaration_no, gross_weight, volume_weight, cbm, chargeable_weight, no_of_pieces, commodity_type, no_of_containers, insurance_needed, container_type, temperature_control_required, container_number, special_comments, landing_country, landing_port, loading_place, billing_city, billing_state, billing_code, billing_country, destination_country, destination_port, fdp, shipping_city, shipping_state, shipping_code, shipping_country, subject, terms_and_conditions, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, customer_notes, grand_tax, grand_total, happy_customer, unhappy_reason, shipment_on_time, referral, notes, books_customer_id, quote_id, project_id, modified_by, customer_type, approved_time, approved_time_resubmission, publish, is_active, created_at, updated_at, updated_by, created_by, pdf FROM `{DB::JOBS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToJob($row);
    }

    /**
     * Get the last job number for a given monthly prefix and organization
     */
    public function getLastJobNoForMonth(string $prefix, int $orgId): ?string
    {
        $sql = "SELECT job_no FROM `{DB::JOBS}` 
                WHERE job_no LIKE :prefix AND organization_id = :org_id 
                ORDER BY job_no DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '-%', 'org_id' => $orgId]);
        return $row !== null ? (string)$row['job_no'] : null;
    }

    /**
     * Find all jobs in an organization
     */
    public function findAll(int $orgId): array
    {
        $sql = "SELECT id, organization_id, job_date, job_status, warehouse_id, customer_id, quotation_id, job_ref_no, job_no, job_seq, sales_person, sales_person_from_lead, project_created, qrcode, currency, exchange_rate, transport_mode, shipment_type, job_owner, tags, services, cs_agent, incoterm, email, supplier_rate, estimated_net_profit, estimated_invoice_amount, etd, eta, carrier, vessel_name, vessel_departure_date, flight_no, flight_departure_date, job_completion_date, payment_terms, hawb, mawb, estimated_cost_amount, declaration_no, gross_weight, volume_weight, cbm, chargeable_weight, no_of_pieces, commodity_type, no_of_containers, insurance_needed, container_type, temperature_control_required, container_number, special_comments, landing_country, landing_port, loading_place, billing_city, billing_state, billing_code, billing_country, destination_country, destination_port, fdp, shipping_city, shipping_state, shipping_code, shipping_country, subject, terms_and_conditions, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, customer_notes, grand_tax, grand_total, happy_customer, unhappy_reason, shipment_on_time, referral, notes, books_customer_id, quote_id, project_id, modified_by, customer_type, approved_time, approved_time_resubmission, publish, is_active, created_at, updated_at, updated_by, created_by, pdf FROM `{DB::JOBS}` WHERE organization_id = :org_id ORDER BY id DESC";
        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);
        $jobs = [];
        foreach ($rows as $row) {
            $jobs[] = $this->mapRowToJob($row);
        }
        return $jobs;
    }

    /**
     * Find by a specific field (for duplicate check)
     */
    public function findByField(string $field, string $value, int $orgId, int $excludeId = 0): ?Job
    {
        $sql = "SELECT id, organization_id, job_date, job_status, warehouse_id, customer_id, quotation_id, job_ref_no, job_no, job_seq, sales_person, sales_person_from_lead, project_created, qrcode, currency, exchange_rate, transport_mode, shipment_type, job_owner, tags, services, cs_agent, incoterm, email, supplier_rate, estimated_net_profit, estimated_invoice_amount, etd, eta, carrier, vessel_name, vessel_departure_date, flight_no, flight_departure_date, job_completion_date, payment_terms, hawb, mawb, estimated_cost_amount, declaration_no, gross_weight, volume_weight, cbm, chargeable_weight, no_of_pieces, commodity_type, no_of_containers, insurance_needed, container_type, temperature_control_required, container_number, special_comments, landing_country, landing_port, loading_place, billing_city, billing_state, billing_code, billing_country, destination_country, destination_port, fdp, shipping_city, shipping_state, shipping_code, shipping_country, subject, terms_and_conditions, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, customer_notes, grand_tax, grand_total, happy_customer, unhappy_reason, shipment_on_time, referral, notes, books_customer_id, quote_id, project_id, modified_by, customer_type, approved_time, approved_time_resubmission, publish, is_active, created_at, updated_at, updated_by, created_by, pdf FROM `{DB::JOBS}` WHERE $field = :value AND organization_id = :org_id";
        $params = ['value' => $value, 'org_id' => $orgId];
        if ($excludeId > 0) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        $row = $this->db->fetchOne($sql, $params);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToJob($row);
    }

    /**
     * Save Job (Insert or Update)
     */
    public function save(Job $job): Job
    {
        if ($job->id === null) {
            return $this->insert($job);
        }
        return $this->update($job);
    }

    private function insert(Job $job): Job
    {
        $sql = "INSERT INTO `{DB::JOBS}` (
                    organization_id, job_date, job_status, warehouse_id, customer_id,
                    quotation_id, job_ref_no, job_no, job_seq, sales_person,
                    sales_person_from_lead, project_created, qrcode,
                    currency, exchange_rate, transport_mode, shipment_type, job_owner, tags,
                    services, cs_agent, incoterm, email, supplier_rate, estimated_net_profit,
                    estimated_invoice_amount, etd, eta, carrier, vessel_name, vessel_departure_date,
                    flight_no, flight_departure_date, job_completion_date, payment_terms, hawb, mawb,
                    estimated_cost_amount, declaration_no, gross_weight, volume_weight, cbm,
                    chargeable_weight, no_of_pieces, commodity_type, no_of_containers,
                    insurance_needed, container_type, temperature_control_required,
                    container_number, special_comments, landing_country, landing_port,
                    loading_place, billing_city, billing_state, billing_code, billing_country,
                    destination_country, destination_port, fdp, shipping_city, shipping_state,
                    shipping_code, shipping_country, subject, terms_and_conditions,
                    grand_subtotal, grand_discount_type, grand_discount_type_value,
                    grand_discount_amount, grand_after_discount, customer_notes, grand_tax,
                    grand_total, happy_customer, unhappy_reason, shipment_on_time, referral,
                    notes, books_customer_id, quote_id, project_id, modified_by, customer_type,
                    approved_time, approved_time_resubmission, publish, is_active,
                    created_at, updated_at, updated_by, created_by, pdf
                ) VALUES (
                    :organization_id, :job_date, :job_status, :warehouse_id, :customer_id,
                    :quotation_id, :job_ref_no, :job_no, :job_seq, :sales_person,
                    :sales_person_from_lead, :project_created, :qrcode,
                    :currency, :exchange_rate, :transport_mode, :shipment_type, :job_owner, :tags,
                    :services, :cs_agent, :incoterm, :email, :supplier_rate, :estimated_net_profit,
                    :estimated_invoice_amount, :etd, :eta, :carrier, :vessel_name, :vessel_departure_date,
                    :flight_no, :flight_departure_date, :job_completion_date, :payment_terms, :hawb, :mawb,
                    :estimated_cost_amount, :declaration_no, :gross_weight, :volume_weight, :cbm,
                    :chargeable_weight, :no_of_pieces, :commodity_type, :no_of_containers,
                    :insurance_needed, :container_type, :temperature_control_required,
                    :container_number, :special_comments, :landing_country, :landing_port,
                    :loading_place, :billing_city, :billing_state, :billing_code, :billing_country,
                    :destination_country, :destination_port, :fdp, :shipping_city, :shipping_state,
                    :shipping_code, :shipping_country, :subject, :terms_and_conditions,
                    :grand_subtotal, :grand_discount_type, :grand_discount_type_value,
                    :grand_discount_amount, :grand_after_discount, :customer_notes, :grand_tax,
                    :grand_total, :happy_customer, :unhappy_reason, :shipment_on_time, :referral,
                    :notes, :books_customer_id, :quote_id, :project_id, :modified_by, :customer_type,
                    :approved_time, :approved_time_resubmission, :publish, :is_active,
                    NOW(), NOW(), :updated_by, :created_by, :pdf
                )";

        $params = $job->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);
        if (($params['etd'] ?? null) === null) {
            $params['etd'] = '1970-01-01';
        }
        if (($params['eta'] ?? null) === null) {
            $params['eta'] = '1970-01-01';
        }
        if (($params['vessel_departure_date'] ?? null) === null) {
            $params['vessel_departure_date'] = '1970-01-01';
        }
        if (($params['flight_departure_date'] ?? null) === null) {
            $params['flight_departure_date'] = '1970-01-01';
        }
        if (($params['job_completion_date'] ?? null) === null) {
            $params['job_completion_date'] = '1970-01-01';
        }

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $job->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted job.");
        }

        return $inserted;
    }

    private function update(Job $job): Job
    {
        $sql = "UPDATE `{DB::JOBS}` SET
                    job_date = :job_date,
                    job_status = :job_status,
                    warehouse_id = :warehouse_id,
                    customer_id = :customer_id,
                    quotation_id = :quotation_id,
                    job_ref_no = :job_ref_no,
                    job_no = :job_no,
                    job_seq = :job_seq,
                    sales_person = :sales_person,
                    sales_person_from_lead = :sales_person_from_lead,
                    project_created = :project_created,
                    qrcode = :qrcode,
                    currency = :currency,
                    exchange_rate = :exchange_rate,
                    transport_mode = :transport_mode,
                    shipment_type = :shipment_type,
                    job_owner = :job_owner,
                    tags = :tags,
                    services = :services,
                    cs_agent = :cs_agent,
                    incoterm = :incoterm,
                    email = :email,
                    supplier_rate = :supplier_rate,
                    estimated_net_profit = :estimated_net_profit,
                    estimated_invoice_amount = :estimated_invoice_amount,
                    etd = :etd,
                    eta = :eta,
                    carrier = :carrier,
                    vessel_name = :vessel_name,
                    vessel_departure_date = :vessel_departure_date,
                    flight_no = :flight_no,
                    flight_departure_date = :flight_departure_date,
                    job_completion_date = :job_completion_date,
                    payment_terms = :payment_terms,
                    hawb = :hawb,
                    mawb = :mawb,
                    estimated_cost_amount = :estimated_cost_amount,
                    declaration_no = :declaration_no,
                    gross_weight = :gross_weight,
                    volume_weight = :volume_weight,
                    cbm = :cbm,
                    chargeable_weight = :chargeable_weight,
                    no_of_pieces = :no_of_pieces,
                    commodity_type = :commodity_type,
                    no_of_containers = :no_of_containers,
                    insurance_needed = :insurance_needed,
                    container_type = :container_type,
                    temperature_control_required = :temperature_control_required,
                    container_number = :container_number,
                    special_comments = :special_comments,
                    landing_country = :landing_country,
                    landing_port = :landing_port,
                    loading_place = :loading_place,
                    billing_city = :billing_city,
                    billing_state = :billing_state,
                    billing_code = :billing_code,
                    billing_country = :billing_country,
                    destination_country = :destination_country,
                    destination_port = :destination_port,
                    fdp = :fdp,
                    shipping_city = :shipping_city,
                    shipping_state = :shipping_state,
                    shipping_code = :shipping_code,
                    shipping_country = :shipping_country,
                    subject = :subject,
                    terms_and_conditions = :terms_and_conditions,
                    grand_subtotal = :grand_subtotal,
                    grand_discount_type = :grand_discount_type,
                    grand_discount_type_value = :grand_discount_type_value,
                    grand_discount_amount = :grand_discount_amount,
                    grand_after_discount = :grand_after_discount,
                    customer_notes = :customer_notes,
                    grand_tax = :grand_tax,
                    grand_total = :grand_total,
                    happy_customer = :happy_customer,
                    unhappy_reason = :unhappy_reason,
                    shipment_on_time = :shipment_on_time,
                    referral = :referral,
                    notes = :notes,
                    books_customer_id = :books_customer_id,
                    quote_id = :quote_id,
                    project_id = :project_id,
                    modified_by = :modified_by,
                    customer_type = :customer_type,
                    approved_time = :approved_time,
                    approved_time_resubmission = :approved_time_resubmission,
                    publish = :publish,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by,
                    pdf = :pdf
                WHERE id = :id AND organization_id = :organization_id";

        $params = $job->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);
        if (($params['etd'] ?? null) === null) {
            $params['etd'] = '1970-01-01';
        }
        if (($params['eta'] ?? null) === null) {
            $params['eta'] = '1970-01-01';
        }
        if (($params['vessel_departure_date'] ?? null) === null) {
            $params['vessel_departure_date'] = '1970-01-01';
        }
        if (($params['flight_departure_date'] ?? null) === null) {
            $params['flight_departure_date'] = '1970-01-01';
        }
        if (($params['job_completion_date'] ?? null) === null) {
            $params['job_completion_date'] = '1970-01-01';
        }

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$job->id, $job->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated job.");
        }

        return $updated;
    }

    /**
     * Delete a job
     */
    public function delete(int $id, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::JOBS}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update job status
     */
    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        $sql = "UPDATE `{DB::JOBS}` SET job_status = :status, updated_at = NOW() 
                WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['status' => $status, 'id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Map database row to Job DTO
     */
    private function mapRowToJob(array $row): Job
    {
        return new Job(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            jobDate: (string)$row['job_date'],
            jobStatus: (string)($row['job_status'] ?? ''),
            warehouseId: (int)($row['warehouse_id'] ?? 0),
            customerId: (int)($row['customer_id'] ?? 0),
            quotationId: isset($row['quotation_id']) ? (int)$row['quotation_id'] : null,
            jobReferenceNo: (string)($row['job_ref_no'] ?? ''),
            jobNo: (string)($row['job_no'] ?? ''),
            jobSeq: (int)($row['job_seq'] ?? 0),
            salesPerson: (int)($row['sales_person'] ?? 0),
            salesPersonFromLead: (int)($row['sales_person_from_lead'] ?? 0),
            currency: (string)($row['currency'] ?? ''),
            exchangeRate: (float)($row['exchange_rate'] ?? 0.0),
            transportMode: (string)($row['transport_mode'] ?? ''),
            shipmentType: (string)($row['shipment_type'] ?? ''),
            jobOwner: (int)($row['job_owner'] ?? 0),
            tags: $row['tags'] !== null ? (string)$row['tags'] : null,
            services: $row['services'] !== null ? (string)$row['services'] : null,
            csAgent: (int)($row['cs_agent'] ?? 0),
            incoterm: (string)($row['incoterm'] ?? ''),
            email: $row['email'] !== null ? (string)$row['email'] : null,
            supplierRate: (float)($row['supplier_rate'] ?? 0.0),
            estimatedNetProfit: (float)($row['estimated_net_profit'] ?? 0.0),
            estimatedInvoiceAmount: (float)($row['estimated_invoice_amount'] ?? 0.0),
            etd: (string)($row['etd'] ?? '1970-01-01'),
            eta: (string)($row['eta'] ?? '1970-01-01'),
            carrier: (int)($row['carrier'] ?? 0),
            vesselName: $row['vessel_name'] !== null ? (string)$row['vessel_name'] : null,
            vesselDepartureDate: (string)($row['vessel_departure_date'] ?? '1970-01-01'),
            flightNo: $row['flight_no'] !== null ? (string)$row['flight_no'] : null,
            flightDepartureDate: (string)($row['flight_departure_date'] ?? '1970-01-01'),
            jobCompletionDate: (string)($row['job_completion_date'] ?? '1970-01-01'),
            paymentTerms: $row['payment_terms'] !== null ? (string)$row['payment_terms'] : null,
            hawb: $row['hawb'] !== null ? (string)$row['hawb'] : null,
            mawb: $row['mawb'] !== null ? (string)$row['mawb'] : null,
            estimatedCostAmount: (float)($row['estimated_cost_amount'] ?? 0.0),
            declarationNo: (string)($row['declaration_no'] ?? ''),
            grossWeight: (float)($row['gross_weight'] ?? 0.0),
            volumeWeight: (float)($row['volume_weight'] ?? 0.0),
            cbm: (float)($row['cbm'] ?? 0.0),
            chargeableWeight: (float)($row['chargeable_weight'] ?? 0.0),
            noOfPieces: (int)($row['no_of_pieces'] ?? 0),
            commodityType: (string)($row['commodity_type'] ?? ''),
            noOfContainers: (int)($row['no_of_containers'] ?? 0),
            insuranceNeeded: (string)($row['insurance_needed'] ?? '0'),
            containerType: (int)($row['container_type'] ?? 0),
            temperatureControlRequired: (string)($row['temperature_control_required'] ?? '0'),
            containerNumber: $row['container_number'] !== null ? (string)$row['container_number'] : null,
            specialComments: $row['special_comments'] !== null ? (string)$row['special_comments'] : null,
            landingCountry: (int)($row['landing_country'] ?? 0),
            landingPort: (int)($row['landing_port'] ?? 0),
            loadingPlace: $row['loading_place'] !== null ? (string)$row['loading_place'] : null,
            billingCity: $row['billing_city'] !== null ? (string)$row['billing_city'] : null,
            billingState: $row['billing_state'] !== null ? (string)$row['billing_state'] : null,
            billingCode: $row['billing_code'] !== null ? (string)$row['billing_code'] : null,
            billingCountry: (int)($row['billing_country'] ?? 0),
            destinationCountry: (int)($row['destination_country'] ?? 0),
            destinationPort: (int)($row['destination_port'] ?? 0),
            fdp: $row['fdp'] !== null ? (string)$row['fdp'] : null,
            shippingCity: $row['shipping_city'] !== null ? (string)$row['shipping_city'] : null,
            shippingState: $row['shipping_state'] !== null ? (string)$row['shipping_state'] : null,
            shippingCode: $row['shipping_code'] !== null ? (string)$row['shipping_code'] : null,
            shippingCountry: (int)($row['shipping_country'] ?? 0),
            subject: ($row['subject'] ?? null) !== null ? (string)$row['subject'] : null,
            termsAndConditions: ($row['terms_and_conditions'] ?? null) !== null ? (string)$row['terms_and_conditions'] : null,
            grandSubtotal: (float)($row['grand_subtotal'] ?? 0.0),
            grandDiscountType: (string)($row['grand_discount_type'] ?? '0.00'),
            grandDiscountTypeValue: (float)($row['grand_discount_type_value'] ?? 0.0),
            grandDiscountAmount: (float)($row['grand_discount_amount'] ?? 0.0),
            grandAfterDiscount: (float)($row['grand_after_discount'] ?? 0.0),
            customerNotes: ($row['customer_notes'] ?? null) !== null ? (string)$row['customer_notes'] : null,
            grandTax: (float)($row['grand_tax'] ?? 0.0),
            grandTotal: (float)($row['grand_total'] ?? 0.0),
            happyCustomer: (string)($row['happy_customer'] ?? ''),
            unhappyReason: $row['unhappy_reason'] !== null ? (string)$row['unhappy_reason'] : null,
            shipmentOnTime: (string)($row['shipment_on_time'] ?? ''),
            referral: $row['referral'] !== null ? (string)$row['referral'] : null,
            notes: $row['notes'] !== null ? (string)$row['notes'] : null,
            booksCustomerId: (int)($row['books_customer_id'] ?? 0),
            quoteId: (int)($row['quote_id'] ?? 0),
            projectId: (int)($row['project_id'] ?? 0),
            projectCreated: (string)($row['project_created'] ?? '0'),
            qrcode: $row['qrcode'] !== null ? (string)$row['qrcode'] : null,
            modifiedBy: $row['modified_by'] !== null ? (string)$row['modified_by'] : null,
            customerType: ($row['customer_type'] ?? null) !== null ? (string)$row['customer_type'] : null,
            approvedTime: $row['approved_time'] !== null ? (string)$row['approved_time'] : null,
            approvedTimeResubmission: $row['approved_time_resubmission'] !== null ? (string)$row['approved_time_resubmission'] : null,
            publish: (bool)($row['publish'] ?? $row['is_active'] ?? false),
            isActive: (bool)($row['is_active'] ?? $row['publish'] ?? true),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
            pdf: $row['pdf'] !== null ? (string)$row['pdf'] : null
        );
    }
}
