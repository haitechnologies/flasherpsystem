---
description: >
  Use when fixing SQL convention violations in the Haizon codebase. Covers
  SELECT *, hard DELETE, mysqli usage, hardcoded table names, and other
  database anti-patterns. Load this skill when doing SQL cleanup work.
---

# Fix SQL — Convention Violations & Fixes

## The Rules

1. Use `App\Core\Database` PDO wrapper with named params
2. Use `DB::*` constants for table names — never hardcode
3. Always scope by `organization_id` on tenant tables
4. No `SELECT *` — specify columns
5. No raw `$mysqli->query()` with interpolation
6. No hard deletes — use `is_active = 0`

## Known Violations (extant, do not replicate)

| Violation | Pattern | Fix |
|-----------|---------|-----|
| `SELECT *` | `SELECT * FROM \`{DB::TABLE}\`` | Replace with explicit column list from Model |
| Hard `DELETE` | `DELETE FROM \`{DB::TABLE}\` WHERE id = :id` | Use `UPDATE \`{DB::TABLE}\` SET is_active = 0 WHERE id = :id` |
| String interpolation | `WHERE id = $id` | Use named params: `WHERE id = :id` with `['id' => $id]` |
| `$mysqli->query()` | `$mysqli->query("SELECT ...")` | Use `$db->fetchAll()` / `$db->fetchOne()` |
| Hardcoded table | `FROM erp_customers` | Use `FROM \`{DB::CUSTOMERS}\`` |
| No org scoping | `WHERE id = :id` missing org filter | Add `AND organization_id = :org_id` |
| `@` error suppression | `@$mysqli->query(...)` | Remove `@`, use try/catch or `$db->execute()` |

## Fix Patterns

### Pattern 1: SELECT * → explicit columns

```diff
- $sql = "SELECT * FROM `{DB::PORTS}` WHERE id = :id";
+ $sql = "SELECT id, port_name, port_code, country_id, is_active, created_by, created_at FROM `{DB::PORTS}` WHERE id = :id";
```

### Pattern 2: Hard DELETE → soft delete

```diff
- $this->db->execute("DELETE FROM `{DB::PORTS}` WHERE id = :id", ['id' => $id]);
+ $this->db->execute("UPDATE `{DB::PORTS}` SET is_active = 0 WHERE id = :id", ['id' => $id]);
```

### Pattern 3: $mysqli query → PDO wrapper

```diff
- $result = $mysqli->query("SELECT * FROM `$tbl_name`");
- while ($row = $result->fetch_assoc()) { ... }
+ $rows = $this->db->fetchAll("SELECT id, name FROM `{DB::PORTS}`");
+ foreach ($rows as $row) { ... }
```

### Pattern 4: String interpolation → named params

```diff
- $sql = "SELECT id FROM `{DB::PORTS}` WHERE name = '$name'";
+ $sql = "SELECT id FROM `{DB::PORTS}` WHERE name = :name";
+ $row = $this->db->fetchOne($sql, ['name' => $name]);
```

### Pattern 5: Missing org scope

```diff
- $sql = "SELECT id, name FROM `{DB::PORTS}` ORDER BY name ASC";
+ $sql = "SELECT id, name FROM `{DB::PORTS}` WHERE organization_id = :org_id ORDER BY name ASC";
+ $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);
```

## Dynamic Update Builder Pattern

For Repository `update()` methods, use this dynamic SET builder:

```php
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
    $sql = "UPDATE `{DB::TABLE}` SET " . implode(', ', $sets) . " WHERE id = :id";
    $this->db->execute($sql, $params);
    return true;
}
```

This avoids the anti-pattern of rebuilding the entire DTO on every update.

## Files Known to Have Violations

These files are known to still have issues — check when editing:

| File | Known Issue |
|------|-------------|
| `src/Repository/CustomerRepository.php` | Hard `DELETE` |
| `src/Repository/*.php` (most) | `SELECT *` in some queries |
| `dashboard/listing_*.php` (procedural files) | `$mysqli->query()` with interpolation |
| `dashboard/bootstrap.php:1132` | Hardcoded org fallback "Flash Logistics FZCO" |
| `dashboard/report_*.php` | `$_REQUEST`, `$GLOBALS`, raw SQL |
| `src/Core/DynamicPrefixMysqli.php` | Legacy mysqli kept for config/database.php |

## Verification Checklist

After fixing SQL in a file:
- [ ] `php -l <file>` passes
- [ ] No `SELECT *` remains
- [ ] All params use named `:param` syntax
- [ ] No `$mysqli->query()` with interpolation
- [ ] Organization scoping present where needed
- [ ] Soft delete instead of hard delete
