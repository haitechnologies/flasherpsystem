---
description: >
  Use when creating a new CRUD module. Follows the standard 6-step layered pattern:
  Model → Repository → Service → Controller → View → Dashboard.
  Do NOT use for HR-protected modules (departments, designations, attendance, leave, payroll, etc.)
  without user approval.
---

# Create CRUD — Haizon Standard Pattern

## File Creation Order

| Step | File | Purpose |
|------|------|---------|
| 1 | `src/Model/{Entity}.php` | Readonly DTO |
| 2 | `src/Repository/{Entity}Repository.php` | PDO CRUD + `DB::*` constants |
| 3 | `src/Service/{Entity}Service.php` | Validation + business logic |
| 4 | `src/Http/Controller/{Entity}Controller.php` | Request handler |
| 5 | `resources/views/{entity}/form.php` | Bootstrap form template |
| 6a | `dashboard/{entity}.php` | 13-line dispatcher |
| 6b | `dashboard/listing_{entity}.php` | DataTable listing |
| 6c | Register in `dashboard/bootstrap.php` | 3 registrations |
| 6d | Register DataTable in `src/DataTable/Registry.php` | `$this->register('listing_{entity}', {Entity}DataTable::class)` |

## Step 1 — Model

```php
<?php

declare(strict_types=1);

namespace App\Model;

readonly class {Entity}
{
    public function __construct(
        public int $id,
        public string $name = '',
        // ... fields matching DB columns
        public bool $isActive = true,
        public int $createdBy = 0,
        public string $createdAt = '',
    ) {}
}
```

Rules:
- `readonly class` with constructor promotion only
- No methods, no setters, no logic
- Property names are `camelCase`; map from `snake_case` DB columns in Repository

## Step 2 — Repository

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\{Entity};

class {Entity}Repository
{
    public function __construct(private Database $db) {}

    public function find(int $id): ?{Entity}
    {
        $sql = "SELECT id, name, is_active, created_by, created_at
                FROM `{DB::{TABLE_CONSTANT}}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(int $orgId): array
    {
        $sql = "SELECT id, name, is_active, created_by, created_at
                FROM `{DB::{TABLE_CONSTANT}}`
                WHERE organization_id = :org_id
                ORDER BY name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql, ['org_id' => $orgId]));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::{TABLE_CONSTANT}}` WHERE name = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::{TABLE_CONSTANT}}` WHERE name = :name LIMIT 1";
        return $this->db->fetchOne($sql, $excludeId !== null
            ? ['name' => $name, 'exclude_id' => $excludeId]
            : ['name' => $name]
        ) !== null;
    }

    public function insert({Entity} $item): int
    {
        $sql = "INSERT INTO `{DB::{TABLE_CONSTANT}}` (name, is_active, created_by)
                VALUES (:name, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'name' => $item->name,
            'is_active' => $item->isActive ? 1 : 0,
            'created_by' => $item->createdBy,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $key = 'u_' . str_replace('.', '_', $col);
            $sets[] = "`{$col}` = :{$key}";
            $params[$key] = $val;
        }
        $params['id'] = $id;
        $sql = "UPDATE `{DB::{TABLE_CONSTANT}}` SET " . implode(', ', $sets) . " WHERE id = :id";
        $this->db->execute($sql, $params);
        return true;
    }

    public function delete(int $id): bool
    {
        $sql = "UPDATE `{DB::{TABLE_CONSTANT}}` SET is_active = 0 WHERE id = :id";
        $this->db->execute($sql, ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): {Entity}
    {
        return new {Entity}(
            id: (int)$row['id'],
            name: (string)($row['name'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
```

Rules:
- Use `DB::*` constants, never hardcode table names
- Named PDO parameters (`:param`) — never string interpolation in SQL
- `SELECT` with explicit column list matching Model properties
- Soft delete via `is_active = 0` unless explicitly told otherwise
- `mapRowToDto()` private method for mapping

## Step 3 — Service

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\{Entity};
use App\Repository\{Entity}Repository;
use App\Exception\ValidationException;

class {Entity}Service
{
    public function __construct(private {Entity}Repository $repo) {}

    public function getById(int $id): ?{Entity}
    {
        return $this->repo->find($id);
    }

    public function list(int $orgId): array
    {
        return $this->repo->findAll($orgId);
    }

    public function create(array $data, int $createdBy): int
    {
        $name = trim((string)($data['name'] ?? ''));

        if ($name === '') {
            throw new ValidationException(['name' => 'Name is mandatory.']);
        }
        if ($this->repo->exists($name)) {
            throw new ValidationException(['name' => 'Name already exists.']);
        }

        $item = new {Entity}(
            id: 0,
            name: $name,
            isActive: (bool)($data['is_active'] ?? true),
            createdBy: $createdBy,
        );

        return $this->repo->insert($item);
    }

    public function update(int $id, array $data, int $updatedBy): bool
    {
        $existing = $this->repo->find($id);
        if ($existing === null) {
            return false;
        }

        $name = trim((string)($data['name'] ?? $existing->name));

        if ($name === '') {
            throw new ValidationException(['name' => 'Name is mandatory.']);
        }
        if ($this->repo->exists($name, $id)) {
            throw new ValidationException(['name' => 'Name already exists.']);
        }

        return $this->repo->update($id, [
            'name' => $name,
            'is_active' => (bool)($data['is_active'] ?? $existing->isActive) ? 1 : 0,
            'updated_by' => $updatedBy,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
```

Rules:
- Validation returns `ValidationException` with field-keyed error array
- Use `$existing` fallback pattern in update (keep old value if not provided)
- No HTML escaping, no redirects — those belong in Controller

## Step 4 — Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Core\DB;
use App\Http\Request;
use App\Http\Response;
use App\Service\{Entity}Service;
use App\Exception\ValidationException;

class {Entity}Controller extends BaseController
{
    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        private {Entity}Service $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('{slug}', '{Caption}');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('{slug}.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_{slug}' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_{slug}' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'name' => $request->post('name', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('{Caption} updated successfully.');
            return Response::redirect('listing_{slug}.php');
        } catch (ValidationException $e) {
            flash_error(current($e->getErrors()));
            return Response::redirect("{slug}.php?id=$id&action=edit_{slug}");
        } catch (\Throwable) {
            flash_error('{Caption} could not be updated.');
            return Response::redirect("{slug}.php?id=$id&action=edit_{slug}");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'name' => $request->post('name', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('{Caption} saved successfully.');
            return Response::redirect('listing_{slug}.php');
        } catch (ValidationException $e) {
            flash_error(current($e->getErrors()));
            return Response::redirect('{slug}.php');
        } catch (\Throwable) {
            flash_error('{Caption} could not be saved.');
            return Response::redirect('{slug}.php');
        }
    }

    private function showForm(int $id): Response
    {
        $name = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('{Caption} not found.');
                return Response::redirect('listing_{slug}.php');
            }
            $name = $item->name;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('{slug}/form.php', [
            'id' => $id,
            'name' => $name,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => '{slug}',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
```

Rules:
- Extends `BaseController` — passes `$db`, `$userId`, `$roleId`, `$orgId` to parent
- `__invoke(Request): Response` pattern — single entry point
- Permission checks before action dispatch
- CSRF validation on POST
- `match (true)` for action dispatch
- `handleCreate` / `handleUpdate` / `showForm` private methods
- Catch `ValidationException` specifically, then fallback `\Throwable`

## Step 5 — View

```php
<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $name
 * @var int $publish
 * @var string $moduleCaption
 * @var string $module
 * @var bool $canCreate
 * @var bool $canEdit
 */
include 'admin_elements/admin_header.php';
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow">
        <div class="page-header-content border-top py-2 px-3">
            <div class="my-1 d-flex align-items-center gap-2">
                <h5 class="mb-0"><?php echo $id > 0 ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
            </div>
            <div class="my-1 d-inline-flex align-items-center me-2">
                <div class="form-check form-check-inline form-switch mb-0">
                    <input type="checkbox" class="form-check-input form-check-input-success"
                           name="is_active" id="is_active"
                           <?php echo $publish ? 'checked="checked"' : ''; ?>
                           form="frm<?php echo $module; ?>">
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
            <div class="my-1">
                <?php if ($id > 0 ? $canEdit : $canCreate) { ?>
                    <button type="submit" form="frm<?php echo $module; ?>"
                            class="btn btn-primary btn-sm me-2">Save</button>
                <?php } ?>
                <a href="listing_<?php echo $module; ?>.php"
                   class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>
    <div class="content-inner">
        <div class="content">
            <?php include 'admin_elements/breadcrumb.php'; ?>
            <form class="steps-basic clearfix" method="post"
                  id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>"
                  action="<?php echo $module; ?>.php">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <?php if ($id > 0) { ?>
                    <input type="hidden" name="action" value="update_<?php echo $module; ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php } else { ?>
                    <input type="hidden" name="action" value="add_<?php echo $module; ?>">
                <?php } ?>
                <div class="card col-lg-6">
                    <div class="card-body clearfix">
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">
                                <span class="text-danger">Name:*</span>
                            </label>
                            <div class="col-lg-9">
                                <input required type="text" name="name"
                                       value="<?php echo htmlspecialchars($name); ?>"
                                       class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'admin_elements/admin_footer.php'; ?>
```

Form partials reference (use instead of writing raw HTML):
- `form_field_text.php` — Text/email/number inputs
- `form_field_select.php` — Select dropdowns (supports `options_html`)
- `form_field_textarea.php` — Textarea inputs
- `form_field_date.php` — Date picker with calendar icon
- `form_card_section.php` — Bootstrap card wrapper
- `form_line_items_table.php` — Dynamic add/remove row table

## Step 6a — Dashboard Dispatcher

`dashboard/{slug}.php`:
```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Http\Request;
use App\Http\Controller\{Entity}Controller;

$request = Request::fromGlobals();
$controller = $container->get({Entity}Controller::class);
$response = $controller($request);
$response->send();
```

## Step 6b — DataTable

`src/DataTable/{Entity}sDataTable.php` extends `BaseDataTable`:
- Define `$table = DB::{TABLE_CONSTANT};`
- `$searchFields` — columns to search
- `$sortableColumns` — index→column map
- `formatRow()` — render each row with badges, links, action buttons
- `getActionButtons($id, $module)` — edit + delete buttons
- Register in `src/DataTable/Registry.php`:
  ```php
  $this->register('listing_{slug}', {Entity}sDataTable::class);
  ```

## Step 6c — bootstrap.php Registration

Three separate additions in `dashboard/bootstrap.php`:

```php
// 1. Repository (near other repository autowires)
$container->autowire(\App\Repository\{Entity}Repository::class);

// 2. Service (near other service autowires)
$container->autowire(\App\Service\{Entity}Service::class);

// 3. Controller (near other controller registrations)
$container->register(\App\Http\Controller\{Entity}Controller::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\{Entity}Controller(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\{Entity}Service::class)
    );
});
```

## Reference Examples

| Module | slug | Best For |
|--------|------|----------|
| Ports | `ports` | Simplest — 4 fields, no org scoping |
| Carriers | `carriers` | Simple with org scoping |
| Banks | `banks` | Simple with org scoping |
| Units | `units` | Minimal — 2 fields |
| PaymentMethods | `payment_methods` | Simple with org scoping |
