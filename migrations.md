# Database Migrations — Live Deployment Guide

This file documents every database migration required to deploy to live. All
migration SQL files live in the `migrations/` directory and are ordered by their
filename date prefix.

> `migrations/` is protected from web access by `migrations/.htaccess`
> (`Require all denied`). Apply migrations via CLI only.

## Pre-deploy checklist

1. **Backup the live database** before applying anything:
   ```bash
   mysqldump -u <user> -p <db_name> > backup_$(date +%Y%m%d_%H%M%S).sql
   ```
2. Confirm the target DB uses the `erp_` table prefix (migration SQL has the
   prefix baked into every statement).
3. Confirm the app is on a single organization (`organization_id = 1`) — every
   seed in these migrations uses `organization_id = 1`.

## How to apply

Apply each file in the order listed below. Two equivalent methods:

**Method A — mysql CLI (recommended on live):**
```bash
mysql -u <user> -p <db_name> < migrations/2026_08_13_create_setup_banks.sql
mysql -u <user> -p <db_name> < migrations/2026_08_13_alter_setup_banks_register_no_nullable.sql
mysql -u <user> -p <db_name> < migrations/2026_08_13_simplify_setup_banks.sql
mysql -u <user> -p <db_name> < migrations/2026_08_13_jobs_string_columns.sql
mysql -u <user> -p <db_name> < migrations/2026_08_13_jobs_col_widen.sql
```

**Method B — PHP (project bootstrap, single statement):**
```php
require_once __DIR__ . '/config/database.php';   // sets $mysqli + autoloader
$files = [
    'migrations/2026_08_13_create_setup_banks.sql',
    'migrations/2026_08_13_alter_setup_banks_register_no_nullable.sql',
    'migrations/2026_08_13_simplify_setup_banks.sql',
    'migrations/2026_08_13_jobs_string_columns.sql',
    'migrations/2026_08_13_jobs_col_widen.sql',
];
foreach ($files as $f) {
    $sql = file_get_contents($f);
    if ($mysqli->multi_query($sql)) {
        do { /* drain results */ } while ($mysqli->next_result());
    }
    if ($mysqli->error) {
        throw new RuntimeException("$f failed: " . $mysqli->error);
    }
}
```

> **Idempotency note:** `create_setup_banks.sql` is idempotent (`CREATE TABLE
> IF NOT EXISTS` + `WHERE NOT EXISTS` guards). The `ALTER TABLE` migrations
> (`alter_..._nullable`, `simplify_...`, `jobs_string_columns`, and
> `jobs_col_widen`) are **not** idempotent — re-running
> them after the column is already dropped/nullable will error with
> "Unknown column". Run each exactly once, in order.

## Migration files (apply in this order)

### 1. `2026_08_13_create_setup_banks.sql`
- Creates table `erp_setup_banks` (bank institutions master data — CBUAE Central
  Bank Register).
- Seeds 61 bank institutions with `organization_id = 1`.
- Registers the `setup_banks` module row in `erp_modules`
  (`slug = 'setup_banks'`, `module_name = 'Banks (Institutions)'`) and its 4
  permissions (`view`/`create`/`edit`/`delete`) in `erp_module_permissions`.

### 2. `2026_08_13_alter_setup_banks_register_no_nullable.sql`
- Makes `erp_setup_banks.register_no` nullable (optional field).
- Superseded by migration #3, but kept for environments that already ran #1
  before #3 existed.

### 3. `2026_08_13_simplify_setup_banks.sql`
- Simplifies `erp_setup_banks` to only `institution_name` + `head_office`.
- Drops `register_no`, `license_type`, `license_category`,
  `identification_number` (their unique indexes drop with the columns).
- Final schema: `id, organization_id, institution_name, head_office, is_active,
  created_at, updated_at, created_by, updated_by`.

### 4. `2026_08_13_jobs_string_columns.sql`
- Fixes `erp_jobs` free-text columns wrongly typed as `INT`
  (`loading_place`, `fdp`, `container_number` → `VARCHAR(255)`).
- Nulls out previously zeroed values (`SET x = NULL WHERE x = 0`) so empty
  values display blank instead of `0`.

### 5. `2026_08_13_jobs_col_widen.sql`
- Widens `erp_jobs` columns that truncated user input: `job_no`
  `VARCHAR(10) → VARCHAR(50)` (the `FL-JBym-XXXX` auto-gen format is 14 chars)
  and `transport_mode` / `shipment_type` `VARCHAR(20) → VARCHAR(100)` (comma-joined
  multi-select values exceeded 20 chars).
- Deactivates the duplicate `pending_approval` job status (`id = 7`) so status
  lookups resolve to the canonical row (`id = 3`).

## Post-deploy verification

```sql
-- setup_banks table + 61 rows
SELECT COUNT(*) FROM erp_setup_banks WHERE organization_id = 1;   -- 61

-- module + permissions registered
SELECT id, slug, module_name FROM erp_modules WHERE slug = 'setup_banks';

-- jobs columns are now VARCHAR(255)
SHOW COLUMNS FROM erp_jobs LIKE 'loading_place';
SHOW COLUMNS FROM erp_jobs LIKE 'fdp';
SHOW COLUMNS FROM erp_jobs LIKE 'container_number';

-- jobs widened columns + deduped status
SHOW COLUMNS FROM erp_jobs LIKE 'job_no';          -- varchar(50)
SHOW COLUMNS FROM erp_jobs LIKE 'transport_mode';  -- varchar(100)
SHOW COLUMNS FROM erp_jobs LIKE 'shipment_type';   -- varchar(100)
SELECT id, job_status, is_active FROM erp_job_statuses WHERE LOWER(job_status) = 'pending_approval';
```
