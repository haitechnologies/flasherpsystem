<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Container;
use App\Core\Database;
use App\Core\ErrorCapture;
use App\Core\View;
use App\Http\Request;
use App\Http\Response;

abstract class BaseController
{
    protected Database $db;
    protected int $userId;
    protected int $roleId;
    protected int $orgId;
    protected View $view;
    protected string $moduleSlug = '';
    protected string $moduleCaption = '';
    protected int $moduleId = 0;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ?View $view = null,
    ) {
        $this->db = $db;
        $this->userId = $userId;
        $this->roleId = $roleId;
        $this->orgId = $orgId;
        $this->view = $view ?? new View(dirname(__DIR__, 3) . '/resources/views');
    }

    protected function requiresModule(string $slug, string $caption): void
    {
        $this->moduleSlug = $slug;
        $this->moduleCaption = $caption;

        $sql = "SELECT id FROM `erp_modules` WHERE slug = :slug LIMIT 1";
        $this->moduleId = (int)($this->db->fetchOne($sql, ['slug' => $slug])['id'] ?? 0);
    }

    protected function canView(): bool
    {
        return function_exists('granted') && granted('view', $this->moduleId);
    }

    protected function canCreate(): bool
    {
        return function_exists('granted') && granted('create', $this->moduleId);
    }

    protected function canEdit(): bool
    {
        return function_exists('granted') && granted('edit', $this->moduleId);
    }

    protected function canDelete(): bool
    {
        return function_exists('granted') && granted('delete', $this->moduleId);
    }

    protected function validateCsrf(Request $request): bool
    {
        if ($request->isPost()) {
            $token = $request->post('csrf_token', '');
            return function_exists('validate_csrf_token') && validate_csrf_token($token);
        }
        return true;
    }

    protected function logError(string $message, string $severity = 'ERROR', array $context = []): void
    {
        ErrorCapture::record($message, $severity, null, null, array_merge([
            'module' => $this->moduleSlug,
            'module_id' => $this->moduleId,
        ], $context));
    }

    abstract public function __invoke(Request $request): Response;
}
