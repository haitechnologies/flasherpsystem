<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Container;
use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\CustomerService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;
use App\Helper\DateHelper;

class CustomerController extends BaseController
{
    private CustomerService $customerService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        CustomerService $customerService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->customerService = $customerService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('customers', 'Customer');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_customers' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_customers' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = $this->buildCustomerData($request, false);

        try {
            $customer = $this->customerService->updateCustomer($id, $data, $this->orgId, $this->userId);

            updateCustomerLogs($id, 'customer', 'edit', $id);
            flash_success('The Customer has been updated successfully.');
            return Response::redirect("customer_overview.php?customer_id=$id");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return $this->showForm($request, $id, $data, $error);
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("customers.php?id=$id&action=edit_customers");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $this->moduleSlug,
                'module_slug' => $this->moduleSlug,
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("customers.php?id=$id&action=edit_customers");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = $this->buildCustomerData($request, true);

        try {
            $newCustomer = $this->customerService->createCustomer($data, $this->orgId, $this->userId);
            $id = $newCustomer->id;

            updateCustomerLogs($id, 'customer', 'add', $id);
            flash_success('The Customer has been saved successfully.');
            return Response::redirect("customer_overview.php?customer_id=$id");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return $this->showForm($request, 0, $data, $error);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $this->moduleSlug,
                'module_slug' => $this->moduleSlug,
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error('The Customer could not be saved.');
            return Response::redirect("customers.php");
        }
    }

    private function buildCustomerData(Request $request, bool $isCreate): array
    {
        $data = [
            'customer_owner' => $request->getString('customer_owner'),
            'payment_term' => $request->getString('payment_term'),
            'customer_status' => $request->getString('customer_status'),
            'customer_source' => $request->getString('customer_source'),
            'assigned_to' => $request->getString('assigned_to'),
            'customer_type' => $request->getString('customer_type', 'business'),
            'salutation' => $request->getString('salutation'),
            'first_name' => $request->getString('first_name'),
            'last_name' => $request->getString('last_name'),
            'display_name' => $request->getString('display_name'),
            'company_name' => $request->getString('company_name'),
            'address' => $request->getString('address'),
            'email' => $request->getString('email'),
            'phone' => $request->getString('phone'),
            'mobile' => $request->getString('mobile'),
            'tax_treatment' => $request->getString('tax_treatment'),
            'trn' => $request->getString('trn'),
            'corporate_tax_number' => $request->getString('corporate_tax_number'),
            'license_number' => $request->getString('license_number'),
            'license_expiry' => $request->getString('license_expiry'),
            'currency' => $request->getString('currency'),
            'exchange_rate' => $request->getString('exchange_rate'),
            'sales_person' => $request->getString('sales_person'),
            'cs_agent' => $request->getString('cs_agent'),
            'lead_category' => $request->getString('lead_category'),
            'rating' => $request->getString('rating'),
            'contacted_date' => $request->getString('contacted_date'),
            'description' => $request->getString('description'),
            'tags' => $this->buildTagsString($request),
            'website' => $request->getString('website'),
            'department' => $request->getString('department'),
            'designation' => $request->getString('designation'),
            'x' => $request->getString('x'),
            'facebook' => $request->getString('facebook'),
            'instagram' => $request->getString('instagram'),
            'photo' => $request->getString('photo'),
            'opening_balance' => $request->getString('opening_balance'),
            'receivable_account_id' => $request->getString('receivable_account_id'),
            'credit_limit' => $request->getString('credit_limit'),
            'discount_type' => $request->getString('discount_type'),
            'discount_type_value' => $request->getString('discount_type_value'),
            'subscription_tier' => $request->getString('subscription_tier'),
            'subscription_expires_at' => $request->getString('subscription_expires_at'),
            'is_active' => $isCreate ? true : ($request->get('publish') ? true : false),
            'publish' => $isCreate ? true : ($request->get('publish') ? true : false),
        ];

        if (!$isCreate && $request->has('approved')) {
            $data['approved'] = (bool)$request->get('approved');
        }

        if (!$isCreate && $request->getString('company_name') === '') {
            unset($data['company_name']);
        }

        return $data;
    }

    private function buildTagsString(Request $request): string
    {
        $tags = $request->post('tags');
        if (is_array($tags)) {
            return implode(', ', $tags);
        }
        return (string)$tags;
    }

    private function showForm(Request $request, int $id, ?array $prefillData = null, ?string $prefillError = null): Response
    {
        $module = 'customers';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $error_message = $prefillError ?? $request->getString('error_message');
        $action = $request->getString('action');

        // Default empty values matching original page
        $customer_owner = '';
        $payment_term = '0';
        $customer_status = '0';
        $customer_source = '0';
        $assigned_to = '0';
        $customer_type = 'business';
        $salutation = '';
        $first_name = '';
        $last_name = '';
        $display_name = '';
        $company_name = '';
        $address = '';
        $email = '';
        $phone = '';
        $mobile = '';
        $tax_treatment = '0';
        $trn = '';
        $corporate_tax_number = '';
        $license_number = '';
        $license_expiry = '';
        $currency = '0';
        $exchange_rate = '1';
        $sales_person = '0';
        $cs_agent = '0';
        $lead_category = '';
        $rating = '0';
        $contacted_date = '';
        $description = '';
        $tags_arr = [];
        $posted_tags_arr = [];
        $tags_string = '';
        $website = '';
        $department = '';
        $designation = '';
        $x = '';
        $facebook = '';
        $instagram = '';
        $opening_balance = '0.00';
        $receivable_account_id = '';
        $is_active = 1;

        // Apply prefill data from validation errors (posted values)
        if ($prefillData !== null) {
            $customer_owner = (string)($prefillData['customer_owner'] ?? $customer_owner);
            $payment_term = (string)($prefillData['payment_term'] ?? $payment_term);
            $customer_status = (string)($prefillData['customer_status'] ?? $customer_status);
            $customer_source = (string)($prefillData['customer_source'] ?? $customer_source);
            $assigned_to = (string)($prefillData['assigned_to'] ?? $assigned_to);
            $customer_type = (string)($prefillData['customer_type'] ?? $customer_type);
            $salutation = (string)($prefillData['salutation'] ?? $salutation);
            $first_name = (string)($prefillData['first_name'] ?? $first_name);
            $last_name = (string)($prefillData['last_name'] ?? $last_name);
            $display_name = (string)($prefillData['display_name'] ?? $display_name);
            $company_name = (string)($prefillData['company_name'] ?? $company_name);
            $address = (string)($prefillData['address'] ?? $address);
            $email = (string)($prefillData['email'] ?? $email);
            $phone = (string)($prefillData['phone'] ?? $phone);
            $mobile = (string)($prefillData['mobile'] ?? $mobile);
            $tax_treatment = (string)($prefillData['tax_treatment'] ?? $tax_treatment);
            $trn = (string)($prefillData['trn'] ?? $trn);
            $corporate_tax_number = (string)($prefillData['corporate_tax_number'] ?? $corporate_tax_number);
            $license_number = (string)($prefillData['license_number'] ?? $license_number);
            $license_expiry = (string)($prefillData['license_expiry'] ?? $license_expiry);
            $currency = (string)($prefillData['currency'] ?? $currency);
            $exchange_rate = (string)($prefillData['exchange_rate'] ?? $exchange_rate);
            $sales_person = (string)($prefillData['sales_person'] ?? $sales_person);
            $cs_agent = (string)($prefillData['cs_agent'] ?? $cs_agent);
            $lead_category = (string)($prefillData['lead_category'] ?? $lead_category);
            $rating = (string)($prefillData['rating'] ?? $rating);
            $contacted_date = (string)($prefillData['contacted_date'] ?? $contacted_date);
            $description = (string)($prefillData['description'] ?? $description);
            $tags_string = (string)($prefillData['tags'] ?? '');
            if ($tags_string !== '') {
                $tags_arr = explode(',', $tags_string);
                $tags_arr = array_map('trim', $tags_arr);
            }
            $website = (string)($prefillData['website'] ?? $website);
            $department = (string)($prefillData['department'] ?? $department);
            $designation = (string)($prefillData['designation'] ?? $designation);
            $x = (string)($prefillData['x'] ?? $x);
            $facebook = (string)($prefillData['facebook'] ?? $facebook);
            $instagram = (string)($prefillData['instagram'] ?? $instagram);
            $opening_balance = (string)($prefillData['opening_balance'] ?? $opening_balance);
            $receivable_account_id = (string)($prefillData['receivable_account_id'] ?? $receivable_account_id);
            $is_active = isset($prefillData['is_active']) ? ((bool)$prefillData['is_active'] ? 1 : 0) : $is_active;
        }

        if ($id > 0) {
            try {
                $customer = $this->customerService->getCustomer($id, $this->orgId);

                $customer_owner = (string)$customer->customerOwner;
                $payment_term = (string)$customer->paymentTerm;
                $customer_status = (string)$customer->customerStatus;
                $customer_source = (string)$customer->customerSource;
                $assigned_to = (string)$customer->assignedTo;
                $customer_type = $customer->customerType;
                $salutation = (string)$customer->salutation;
                $first_name = (string)$customer->firstName;
                $last_name = (string)$customer->lastName;
                $display_name = $customer->displayName;
                $company_name = (string)$customer->companyName;
                $address = $customer->address;
                $email = (string)$customer->email;
                $phone = (string)$customer->phone;
                $mobile = (string)$customer->mobile;
                $tax_treatment = (string)$customer->taxTreatment;
                $trn = (string)$customer->trn;
                $corporate_tax_number = (string)$customer->corporateTaxNumber;
                $license_number = (string)$customer->licenseNumber;
                $license_expiry = $customer->licenseExpiry === '1970-01-01' ? '' : DateHelper::toInputDate($customer->licenseExpiry);
                $currency = (string)$customer->currency;
                $exchange_rate = (string)$customer->exchangeRate;
                $sales_person = (string)$customer->salesPerson;
                $cs_agent = (string)$customer->csAgent;
                $lead_category = (string)$customer->leadCategory;
                $rating = (string)$customer->rating;
                $contacted_date = $customer->contactedDate ? DateHelper::toDisplayDateTime($customer->contactedDate) : '';
                $description = (string)$customer->description;
                $tags_value = (string)$customer->tags;
                if ($tags_value !== '') {
                    $tags_arr = explode(',', $tags_value);
                }
                $website = (string)$customer->website;
                $department = (string)$customer->department;
                $designation = (string)$customer->designation;
                $x = (string)$customer->x;
                $facebook = (string)$customer->facebook;
                $instagram = (string)$customer->instagram;
                $opening_balance = number_format((float)($customer->openingBalance ?? 0), 2, '.', '');
                $receivable_account_id = $customer->receivableAccountId !== null ? (string)$customer->receivableAccountId : '';
                $is_active = $customer->isActive ? 1 : 0;
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        // Fetch dropdown data from PDO
        try {
            $tagsList = $this->db->fetchAll("SELECT id, value FROM `" . DB::TAXONOMIES . "` WHERE is_active=1 AND type='customer_tag' AND organization_id=:org_id ORDER BY value", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $tagsList = [];
            if (function_exists('log_error')) {
                log_error('customers form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]) : ['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $statusesList = $this->db->fetchAll("SELECT id, value FROM `" . DB::TAXONOMIES . "` WHERE is_active=1 AND type='customer_status' AND organization_id=:org_id ORDER BY value", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $statusesList = [];
            if (function_exists('log_error')) {
                log_error('customers form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]) : ['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $sourcesList = $this->db->fetchAll("SELECT id, value FROM `" . DB::TAXONOMIES . "` WHERE is_active=1 AND type='customer_source' AND organization_id=:org_id ORDER BY value", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $sourcesList = [];
            if (function_exists('log_error')) {
                log_error('customers form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]) : ['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $usersList = $this->db->fetchAll("SELECT id, full_name FROM `" . DB::USERS . "` WHERE is_active=1 AND organization_id=:org_id ORDER BY full_name", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $usersList = [];
            if (function_exists('log_error')) {
                log_error('customers form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]) : ['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $departmentsList = $this->db->fetchAll("SELECT id, department, email FROM `" . DB::DEPARTMENTS . "` WHERE publish=1 AND organization_id=:org_id ORDER BY department", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $departmentsList = [];
            if (function_exists('log_error')) {
                log_error('customers form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]) : ['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $taxTreatmentsList = $this->db->fetchAll("SELECT id, tax_treatment FROM `" . DB::TAX_TREATMENTS . "` WHERE is_active=1 AND organization_id=:org_id ORDER BY id ASC", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $taxTreatmentsList = [];
            if (function_exists('log_error')) {
                log_error('customers form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]) : ['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $currencyList = $this->db->fetchAll("SELECT id, currency FROM `" . DB::CURRENCIES . "` WHERE is_active=1 AND organization_id=:org_id ORDER BY id ASC", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $currencyList = [];
            if (function_exists('log_error')) {
                log_error('customers form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]) : ['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $arAccountsList = $this->db->fetchAll("SELECT id, account_name, account_code FROM `" . DB::ACCOUNTS . "` WHERE is_active=1 AND account_type='Assets' ORDER BY account_name");
        } catch (\Throwable $e) {
            $arAccountsList = [];
            if (function_exists('log_error')) {
                log_error('customers form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]) : ['module' => 'customers', 'module_slug' => 'customers', 'error_code' => (string)$e->getCode()]);
            }
        }

        return Response::html($this->view->render('customers/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'error_message' => $error_message,
            'customer_type' => $customer_type,
            'salutation' => $salutation,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name,
            'address' => $address,
            'email' => $email,
            'phone' => $phone,
            'mobile' => $mobile,
            'contacted_date' => $contacted_date,
            'description' => $description,
            'tagsList' => $tagsList,
            'tags_arr' => $tags_arr,
            'statusesList' => $statusesList,
            'customer_status' => $customer_status,
            'sourcesList' => $sourcesList,
            'customer_source' => $customer_source,
            'usersList' => $usersList,
            'departmentsList' => $departmentsList,
            'assigned_to' => $assigned_to,
            'is_active' => $is_active,
            'customer_owner' => $customer_owner,
            'payment_term' => $payment_term,
            'taxTreatmentsList' => $taxTreatmentsList,
            'tax_treatment' => $tax_treatment,
            'trn' => $trn,
            'corporate_tax_number' => $corporate_tax_number,
            'license_number' => $license_number,
            'license_expiry' => $license_expiry,
            'opening_balance' => $opening_balance,
            'receivable_account_id' => $receivable_account_id,
            'arAccountsList' => $arAccountsList,
            'currencyList' => $currencyList,
            'currency' => $currency,
            'exchange_rate' => $exchange_rate,
            'sales_person' => $sales_person,
            'lead_category' => $lead_category,
            'cs_agent' => $cs_agent,
            'rating' => $rating,
            'website' => $website,
            'department' => $department,
            'designation' => $designation,
            'x' => $x,
            'facebook' => $facebook,
            'instagram' => $instagram,
            'canCreate' => $this->canCreate(),
            'canView' => $this->canView(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
