<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\BannedWordService;
use App\Exception\ValidationException;

class BannedWordController extends BaseController
{
    private BannedWordService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        BannedWordService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('words', 'Banned Word');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('words.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_words' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_words' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'word' => $request->post('word', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Banned Word has been updated successfully.');
            return Response::redirect('listing_words.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("words.php?id=$id&action=edit_words");
        } catch (\Throwable $e) {
            $this->logError("BannedWordController::handleUpdate error: " . $e->getMessage());
            flash_error('The Banned Word could not be updated.');
            return Response::redirect("words.php?id=$id&action=edit_words");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'word' => $request->post('word', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Banned Word has been saved successfully.');
            return Response::redirect('listing_words.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("words.php");
        } catch (\Throwable $e) {
            $this->logError("BannedWordController::handleCreate error: " . $e->getMessage());
            flash_error('The Banned Word could not be saved.');
            return Response::redirect("words.php");
        }
    }

    private function showForm(int $id): Response
    {
        $word = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_words.php');
            }
            $word = $item->word;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('banned_words/form.php', [
            'id' => $id,
            'word' => $word,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'words',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
