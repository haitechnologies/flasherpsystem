---
description: >
  Use when migrating procedural dashboard code to the layered architecture pattern
  (Controller → Service → Repository → Model). Covers the 8-phase migration plan.
  Do NOT use for general feature work or HR module migrations without user approval.
---

# Migrate Repository — Haizon Architecture Migration

## The 6-Layer Target Pattern

```
dashboard/{module}.php (13-line dispatcher)
    → Controller (__invoke, permission checks, CSRF, match dispatch)
        → Service (validation, business logic, throws ValidationException)
            → Repository (PDO with DB::* constants, named params)
                → Model (readonly DTO)
```

## Migration Steps per Module

### 1. Create Model (`src/Model/{Entity}.php`)

```php
<?php

declare(strict_types=1);

namespace App\Model;

readonly class {Entity}
{
    public function __construct(
        public int $id,
        // ... map ALL columns from the table
        public int $createdBy = 0,
        public string $createdAt = '',
    ) {}
}
```

- Map every column used in the listing and form
- Match database `snake_case` → PHP `camelCase`
- Set sensible defaults for optional columns

### 2. Create Repository (`src/Repository/{Entity}Repository.php`)

- `find(int $id): ?{Entity}`
- `findAll(int $orgId): array` — always scope by organization
- `exists(string $name, ?int $excludeId): bool` — unique validation
- `insert({Entity} $item): int` — returns new ID
- `update(int $id, array $data): bool` — dynamic SET builder
- `delete(int $id): bool` — soft delete (`is_active = 0`) unless instructed otherwise
- Private `mapRowToDto(array $row): {Entity}`
- Use `DB::{TABLE_CONSTANT}` — never hardcode table names
- Named parameters (`:param`) — never string interpolation in SQL
- Explicit column list in SELECT — never `SELECT *`
- Org-scoped WHERE: `WHERE organization_id = :org_id`

### 3. Create Service (`src/Service/{Entity}Service.php`)

- `getById(int $id): ?{Entity}`
- `list(int $orgId): array`
- `create(array $data, int $createdBy): int` — validate, build DTO, insert
- `update(int $id, array $data, int $updatedBy): bool` — validate existing, update
- `delete(int $id): bool`
- Throw `ValidationException` with field-keyed errors on validation failure
- Use `$existing->field` fallback in update when field not provided

### 4. Create Controller (`src/Http/Controller/{Entity}Controller.php`)

Extends `BaseController`. Follow the PortController pattern:

```php
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
        if (!$this->canView()) return new Response('Forbidden', 403);
        if ($request->isPost() && !$this->validateCsrf($request)) { ... }

        return match (true) {
            $request->isPost() && $action === 'update_{slug}' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_{slug}' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }
}
```

- Use `$this->db` (from BaseController) for additional DB queries in form prep
- `showForm()` does `$this->view->render('{slug}/form.php', [...])`

### 5. Create View (`resources/views/{slug}/form.php`)

- Include `admin_elements/admin_header.php` at top
- Include `admin_elements/admin_footer.php` at bottom
- Bootstrap 5 form with CSRF token hidden input
- `action` and `id` hidden inputs for create vs update
- Use form partials where possible: `form_field_text.php`, `form_field_select.php`, etc.

### 6. Update Dashboard Files

**Dashboard dispatcher** (`dashboard/{slug}.php`):
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

**Listing file** (`dashboard/listing_{slug}.php`):
- Usually stays procedural (uses `listing_template.php` and DataTable)
- Clean up: replace raw `$mysqli->query()` with calls to Repository if needed
- Keep the DataTable configuration and AJAX handlers

### 7. Register in bootstrap.php

In `dashboard/bootstrap.php`:
```php
$container->autowire(\App\Repository\{Entity}Repository::class);
$container->autowire(\App\Service\{Entity}Service::class);

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

### 8. Register DataTable

In `src/DataTable/Registry.php`:
```php
$this->register('listing_{slug}', {Entity}sDataTable::class);
```

## What NOT To Do

- ❌ Don't create methods in Model — it's a readonly DTO
- ❌ Don't put SQL in Controller or Service — all SQL in Repository
- ❌ Don't use `$mysqli` or raw `query()` — use `$this->db->fetchOne/fetchAll/execute/insert`
- ❌ Don't hardcode table names — use `DB::*` constants
- ❌ Don't use `SELECT *` — use explicit column lists
- ❌ Don't hard delete — use `is_active = 0`
- ❌ Don't bypass container — use `$container->get()` not `new ServiceClass()`

## Verification

After each migrated module, verify:
1. `php -l` on every new/modified file
2. The form page loads and saves correctly
3. The listing page renders with DataTable
4. Permissions still work (view/create/edit/delete)
5. Search and pagination in DataTable work

## Migration Priority

Phase 1: Simple CRUD (no FK dependencies) → Ports, Carriers, Units, Banks
Phase 2: Master data → Customers, Vendors, Items
Phase 3: Financial docs → Invoices, Purchases, Sale Orders, Quotations
Phase 4: Complex → Payroll, Shipping, Reports
