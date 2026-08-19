<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Core\DB;
use App\Http\Request;
use App\Http\Response;
use App\Service\ConsigneeService;
use App\Exception\ValidationException;

class ConsigneeController extends BaseController
{
    private ConsigneeService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ConsigneeService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('consignees', 'Consignee');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('consignees.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_consignees' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_consignees' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $updated = $this->service->update($id, [
                'consignee_name' => $request->post('consignee_name', ''),
                'address_line1' => $request->post('address_line1', ''),
                'address_line2' => $request->post('address_line2', ''),
                'city' => $request->post('city', ''),
                'zipcode' => $request->post('zipcode', ''),
                'province' => $request->post('province', ''),
                'country' => $request->getInt('country'),
                'email' => $request->post('email', ''),
                'telephone' => $request->post('telephone', ''),
                'mobile' => $request->post('mobile', ''),
                'fax' => $request->post('fax', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            if (!$updated) {
                $this->logError("ConsigneeController::handleUpdate failed to persist record id={$id}");
                flash_error('The Consignee could not be updated. Please check your entries and try again.');
                return Response::redirect("consignees.php?id=$id&action=edit_consignees");
            }
            flash_success('The Consignee has been updated successfully.');
            return Response::redirect('listing_consignees.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("consignees.php?id=$id&action=edit_consignees");
        } catch (\Throwable $e) {
            $this->logError("ConsigneeController::handleUpdate error: " . $e->getMessage());
            flash_error('The Consignee could not be updated.');
            return Response::redirect("consignees.php?id=$id&action=edit_consignees");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'consignee_name' => $request->post('consignee_name', ''),
                'address_line1' => $request->post('address_line1', ''),
                'address_line2' => $request->post('address_line2', ''),
                'city' => $request->post('city', ''),
                'zipcode' => $request->post('zipcode', ''),
                'province' => $request->post('province', ''),
                'country' => $request->getInt('country'),
                'email' => $request->post('email', ''),
                'telephone' => $request->post('telephone', ''),
                'mobile' => $request->post('mobile', ''),
                'fax' => $request->post('fax', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Consignee has been saved successfully.');
            return Response::redirect('listing_consignees.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("consignees.php");
        } catch (\Throwable $e) {
            $this->logError("ConsigneeController::handleCreate error: " . $e->getMessage());
            flash_error('The Consignee could not be saved.');
            return Response::redirect("consignees.php");
        }
    }

    private function showForm(int $id): Response
    {
        $consigneeName = '';
        $addressLine1 = '';
        $addressLine2 = '';
        $city = '';
        $zipcode = '';
        $province = '';
        $country = 0;
        $email = '';
        $telephone = '';
        $mobile = '';
        $fax = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_consignees.php');
            }
            $consigneeName = $item->consigneeName;
            $addressLine1 = $item->addressLine1;
            $addressLine2 = $item->addressLine2;
            $city = $item->city;
            $zipcode = $item->zipcode;
            $province = $item->province;
            $country = $item->country;
            $email = $item->email;
            $telephone = $item->telephone;
            $mobile = $item->mobile;
            $fax = $item->fax;
            $publish = $item->isActive ? 1 : 0;
        }

        try {
            $countriesList = $this->db->fetchAll("SELECT id, country FROM `" . DB::GEO_COUNTRIES . "` WHERE is_active=1 ORDER BY country");
        } catch (\Throwable $e) {
            $countriesList = [];
        }

        return Response::html($this->view->render('consignees/form.php', [
            'id' => $id,
            'consigneeName' => $consigneeName,
            'addressLine1' => $addressLine1,
            'addressLine2' => $addressLine2,
            'city' => $city,
            'zipcode' => $zipcode,
            'province' => $province,
            'country' => $country,
            'email' => $email,
            'telephone' => $telephone,
            'mobile' => $mobile,
            'fax' => $fax,
            'countriesList' => $countriesList,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'consignees',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
