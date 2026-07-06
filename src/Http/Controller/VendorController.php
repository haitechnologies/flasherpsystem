<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\VendorService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;
use App\Helper\DateHelper;

class VendorController extends BaseController
{
    private VendorService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        VendorService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('vendors', 'Vendor');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('vendors.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_vendors' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_vendors' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = $this->buildVendorData($request, false);

        try {
            $this->service->updateVendor($id, $data, $this->orgId, $this->userId);
            flash_success('The Vendor has been updated successfully.');
            return Response::redirect("vendor_overview.php?vendor_id=$id");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("vendors.php?id=$id&action=edit_vendors");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("vendors.php?id=$id&action=edit_vendors");
        } catch (\Throwable $e) {
            flash_error('The Vendor could not be updated.');
            return Response::redirect("vendors.php?id=$id&action=edit_vendors");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = $this->buildVendorData($request, true);

        try {
            $new = $this->service->createVendor($data, $this->orgId, $this->userId);
            flash_success('The Vendor has been saved successfully.');
            return Response::redirect("vendor_overview.php?vendor_id=$new->id");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("vendors.php");
        } catch (\Throwable $e) {
            flash_error('The Vendor could not be saved.');
            return Response::redirect("vendors.php");
        }
    }

    private function buildVendorData(Request $request, bool $isCreate): array
    {
        return [
            'vendor_owner' => $request->getString('vendor_owner'),
            'payment_term' => $request->getString('payment_term'),
            'vendor_status' => $request->getString('vendor_status'),
            'vendor_source' => $request->getString('vendor_source'),
            'assigned_to' => $request->getString('assigned_to'),
            'vendor_type' => $request->getString('vendor_type', ''),
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
            'is_active' => $request->get('publish') ? true : false,
            'approved' => $request->get('approved') ? true : false,
            'publish' => $request->get('publish') ? true : true,
        ];
    }

    private function buildTagsString(Request $request): string
    {
        $tags = $request->post('tags');
        if (is_array($tags)) {
            return implode(', ', $tags);
        }
        return (string)$tags;
    }

    private function showForm(Request $request, int $id): Response
    {
        $module = 'vendors';
        $moduleCaption = $this->moduleCaption;
        $session_user_id = $this->userId;
        $error_message = $request->getString('error_message');
        if (empty($error_message)) {
            foreach (\App\Core\FlashMessage::all() as $fm) {
                if ($fm['type'] === 'danger') { $error_message = $fm['message']; break; }
            }
        }

        $vendor_owner = '';
        $payment_term = '0';
        $vendor_status = '0';
        $vendor_source = '0';
        $assigned_to = '0';
        $vendor_type = '';
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
        $website = '';
        $department = '';
        $designation = '';
        $x = '';
        $facebook = '';
        $instagram = '';
        $is_active = 1;

        if ($id > 0) {
            try {
                $item = $this->service->getVendor($id, $this->orgId);

                $vendor_owner = (string)$item->vendorOwner;
                $payment_term = (string)$item->paymentTerm;
                $vendor_status = (string)$item->vendorStatus;
                $vendor_source = (string)$item->vendorSource;
                $assigned_to = (string)$item->assignedTo;
                $vendor_type = $item->vendorType;
                $salutation = (string)$item->salutation;
                $first_name = (string)$item->firstName;
                $last_name = (string)$item->lastName;
                $display_name = $item->displayName;
                $company_name = (string)$item->companyName;
                $address = $item->address;
                $email = (string)$item->email;
                $phone = (string)$item->phone;
                $mobile = (string)$item->mobile;
                $tax_treatment = (string)$item->taxTreatment;
                $trn = (string)$item->trn;
                $corporate_tax_number = (string)$item->corporateTaxNumber;
                $license_number = (string)$item->licenseNumber;
                $license_expiry = $item->licenseExpiry === '1970-01-01' ? '' : DateHelper::toDbDate($item->licenseExpiry);
                $currency = (string)$item->currency;
                $exchange_rate = (string)$item->exchangeRate;
                $sales_person = (string)$item->salesPerson;
                $cs_agent = (string)$item->csAgent;
                $lead_category = (string)$item->leadCategory;
                $rating = (string)$item->rating;
                $contacted_date = $item->contactedDate ? DateHelper::toDbDateTime($item->contactedDate) : '';
                $description = (string)$item->description;
                $tags_value = (string)$item->tags;
                if ($tags_value !== '') {
                    $tags_arr = explode(',', $tags_value);
                }
                $website = (string)$item->website;
                $department = (string)$item->department;
                $designation = (string)$item->designation;
                $x = (string)$item->x;
                $facebook = (string)$item->facebook;
                $instagram = (string)$item->instagram;
                $is_active = $item->isActive ? 1 : 0;
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        try {
            $tagsList = $this->db->fetchAll("SELECT id, value FROM `" . DB::TAXONOMIES . "` WHERE is_active=1 AND type='vendor_tag' ORDER BY value");
        } catch (\Throwable $e) {
            $tagsList = [];
        }
        try {
            $statusesList = $this->db->fetchAll("SELECT id, value FROM `" . DB::TAXONOMIES . "` WHERE is_active=1 AND type='vendor_status' ORDER BY value");
        } catch (\Throwable $e) {
            $statusesList = [];
        }
        try {
            $sourcesList = $this->db->fetchAll("SELECT id, value FROM `" . DB::TAXONOMIES . "` WHERE is_active=1 AND type='vendor_source' ORDER BY value");
        } catch (\Throwable $e) {
            $sourcesList = [];
        }
        try {
            $usersList = $this->db->fetchAll("SELECT id, full_name FROM `" . DB::USERS . "` WHERE is_active=1 ORDER BY full_name");
        } catch (\Throwable $e) {
            $usersList = [];
        }
        try {
            $taxTreatmentsList = $this->db->fetchAll("SELECT id, tax_treatment FROM `" . DB::TAX_TREATMENTS . "` WHERE is_active=1 ORDER BY id ASC");
        } catch (\Throwable $e) {
            $taxTreatmentsList = [];
        }
        try {
            $currencyList = $this->db->fetchAll("SELECT id, currency FROM `" . DB::CURRENCIES . "` WHERE is_active=1 ORDER BY id ASC");
        } catch (\Throwable $e) {
            $currencyList = [];
        }

        return Response::html($this->view->render('vendors/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'session_user_id' => $session_user_id,
            'error_message' => $error_message,
            'vendor_type' => $vendor_type,
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
            'vendor_status' => $vendor_status,
            'sourcesList' => $sourcesList,
            'vendor_source' => $vendor_source,
            'usersList' => $usersList,
            'assigned_to' => $assigned_to,
            'is_active' => $is_active,
            'vendor_owner' => $vendor_owner,
            'taxTreatmentsList' => $taxTreatmentsList,
            'tax_treatment' => $tax_treatment,
            'trn' => $trn,
            'corporate_tax_number' => $corporate_tax_number,
            'license_number' => $license_number,
            'license_expiry' => $license_expiry,
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
