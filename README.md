# Flash ERP System

> **Business directory, classifieds, and ERP platform** by Hai Technologies.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.2+ (`declare(strict_types=1)`) |
| Framework | None — custom platform, PSR-4 `App\` → `src/` |
| Frontend | Bootstrap 5.3, jQuery 3.7, DataTables.js |
| Database | MySQL 8.0+ / InnoDB / utf8mb4_unicode_ci / PDO |
| Multi-Tenancy | Row-level isolation via `organization_id` (~70 tables) |
| Auth | Session-based + TOTP MFA |
| Payments | Stripe |
| PDF | TCPDF |
| Mail | PHPMailer 6.9 |

## Quick Start

```bash
git clone <repo>
cd flasherpsystem
cp .env.example .env     # Configure DB, Stripe, email
composer install
# Schema auto-init on first run via DatabaseSchemaInitializer (migration CLI removed).
# Future schema changes: use DB CLI/SQL.
```

Serve via Apache (`.htaccess` included) or PHP built-in server.

## Key Modules

- **CRM** — Customers, Contacts, Leads, Projects, Jobs
- **Sales** — Quotations, Invoices, Sale Orders, Credit/Debit Notes
- **Purchasing** — Vendors, Purchases, Purchase Orders
- **Accounting** — Chart of Accounts, Journals, Expenses, Payments
- **HR & Payroll** — Attendance, Leave, Payroll Runs, Payslips
- **Shipping & Logistics** — Shipping Advices, Invoices, Ports, Carriers
- **Subscriptions / SaaS** — Plans, Subscriptions, Features, Overrides
- **System** — Users, Roles, Permissions, Modules, Settings

## Documentation

| File | Purpose |
|------|---------|
| `docs/AGENTS.md` | **AI agent context** — DeepSeek-optimized coding guide |
| `docs/MODULES.md` | Module slug → DataTable handler → controller map |
| `docs/ARCHITECTURE.md` | Architecture layers, key files, multi-tenancy |
| `docs/DATABASE.md` | DB engine, polymorphic tables, org-scoping |
| `docs/MANPOWER_FLOW.md` | Org hierarchy, employee lifecycle, approval workflow |
| `docs/MIGRATION-AUDIT-REMAINING.md` | Migration status, remaining modules |
| `docs/ARCHITECTURE-MIGRATION-PLAN.md` | 8-phase migration execution plan |
