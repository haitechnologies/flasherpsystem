<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Core\DB;
use App\Http\Request;
use App\Http\Response;
use App\Service\PortService;
use App\Exception\ValidationException;

class PortController extends BaseController
{
    private PortService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        PortService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('ports', 'Port');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('ports.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_ports' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_ports' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'port_name' => $request->post('port_name', ''),
                'port_code' => $request->post('port_code', ''),
                'country_id' => (int)$request->post('country_id', 0),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('Port updated successfully.');
            return Response::redirect('listing_ports.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("ports.php?id=$id&action=edit_ports");
        } catch (\Throwable $e) {
            $this->logError("PortController::handleUpdate error: " . $e->getMessage());
            flash_error('Port could not be updated.');
            return Response::redirect("ports.php?id=$id&action=edit_ports");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'port_name' => $request->post('port_name', ''),
                'port_code' => $request->post('port_code', ''),
                'country_id' => (int)$request->post('country_id', 0),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('Port saved successfully.');
            return Response::redirect('listing_ports.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("ports.php");
        } catch (\Throwable $e) {
            $this->logError("PortController::handleCreate error: " . $e->getMessage());
            flash_error('Port could not be saved.');
            return Response::redirect("ports.php");
        }
    }

    private function showForm(int $id): Response
    {
        $portName = '';
        $portCode = '';
        $countryId = 0;
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Port not found.');
                return Response::redirect('listing_ports.php');
            }
            $portName = $item->portName;
            $portCode = $item->portCode;
            $countryId = $item->countryId;
            $publish = $item->isActive ? 1 : 0;
        }

        $countries = $this->db->fetchAll("SELECT id, country AS name FROM `" . DB::GEO_COUNTRIES . "` ORDER BY country ASC");

        return Response::html($this->view->render('ports/form.php', [
            'id' => $id,
            'portName' => $portName,
            'portCode' => $portCode,
            'countryId' => $countryId,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'ports',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'countries' => $countries,
        ]));
    }
}
