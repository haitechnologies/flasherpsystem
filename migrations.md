# Database Migrations — Live Deployment Guide

This file documents database migrations that are **pending deployment to live**.
Once a migration is applied on live, remove it from this file and note it in the
`CHANGELOG.md` release section.

> `migrations/` is protected from web access by `migrations/.htaccess`
> (`Require all denied`). Apply migrations via CLI only.

## Baseline

- **As of 2026-08-13 (deploy commit `b2a9129`):** ALL migrations listed in the
  previous revision of this file are **APPLIED on live**:
  - `2026_08_13_create_setup_banks.sql`
  - `2026_08_13_alter_setup_banks_register_no_nullable.sql`
  - `2026_08_13_simplify_setup_banks.sql`
  - `2026_08_13_jobs_string_columns.sql`
  - `2026_08_13_jobs_col_widen.sql`
  - `2026_08_13_all_in_one_live.sql` (combined single-file runner for the above)
- Live DB verified: `erp_setup_banks` present with 61 rows, `setup_banks` module
  + permissions registered, jobs string/width columns applied, `pending_approval`
  job status deduped.
- There are **no pending migrations** at this time.

## Pending migrations (apply in this order)

_(none — add new migration files here as they are created)_

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

**Method A — mysql CLI (recommended on live):**
```bash
mysql -u <user> -p <db_name> < migrations/<FILE>.sql
```

**Method B — PHP (project bootstrap, single statement):**
```php
require_once __DIR__ . '/config/database.php';   // sets $mysqli + autoloader
$files = [
    'migrations/<FILE>.sql',
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

> Keep migrations idempotent where possible (`CREATE TABLE IF NOT EXISTS`,
> `WHERE NOT EXISTS` guards, `SET @sql := IF(...)` ALTER guards) so re-running
> them is safe.
