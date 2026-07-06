# Manpower Flow Charts — Haizon ERP

> **⚠️ PROTECTED FILE**: Do NOT delete, rename, or move this file without explicit user approval.

> Designed from the existing codebase data model (HR, CRM, Accounting, Shipping modules).
> All diagrams are [Mermaid](https://mermaid.js.org/) syntax — render in any Mermaid-compatible viewer.

---

## 1. Organizational Hierarchy Chart

Shows the reporting and structural hierarchy: Organization → Departments → Designations → Employees → Roles.

```mermaid
graph TD
    Organization["🏢 Organization<br/>(erp_organizations)"] --> Dept1["📁 Sales Dept<br/>(erp_departments)"]
    Organization --> Dept2["📁 Operations Dept"]
    Organization --> Dept3["📁 Accounts Dept"]
    Organization --> Dept4["📁 HR Dept"]

    Dept1 --> Desig1["🧑‍💼 Sales Manager<br/>(erp_designations)"]
    Dept1 --> Desig2["🧑‍💼 Sales Executive"]

    Dept2 --> Desig4["🧑‍💼 Operations Manager"]
    Dept2 --> Desig5["🧑‍💼 Shipping Coordinator"]

    Dept3 --> Desig7["🧑‍💼 Finance Manager"]
    Dept3 --> Desig8["🧑‍💼 Accountant"]

    Dept4 --> Desig10["🧑‍💼 HR Manager"]

    Desig1 --- Emp1["👤 John (SYSTEM_ADMIN)<br/>(erp_users — role_id=1)"]
    Desig2 --- Emp2["👤 Sarah (SALES — role_id=3)"]
    Desig2 --- Emp3["👤 Mike (SALES — role_id=3)"]
    Desig5 --- Emp4["👤 Ahmed (OPERATIONS — role_id=4)"]
    Desig8 --- Emp5["👤 Lisa (ACCOUNTS — role_id=5)"]

    style Organization fill:#e1f5fe,stroke:#01579b
    style Dept1 fill:#fff3e0,stroke:#e65100
    style Dept2 fill:#fff3e0,stroke:#e65100
    style Dept3 fill:#fff3e0,stroke:#e65100
    style Dept4 fill:#fff3e0,stroke:#e65100
```

**Data mapping:**
- `erp_organizations.id` — multi-tenant root
- `erp_departments.id` → `erp_users.department_id`
- `erp_designations.id` → `erp_users.designation_id`
- `erp_roles.id` → `erp_users.role_id` (1=SYSTEM_ADMIN, 2=SUPER_ADMIN, 3=SALES, 4=OPERATIONS, 5=ACCOUNTS)

---

## 2. Employee Lifecycle Flow

Tracks an employee's journey from onboarding through offboarding.

```mermaid
flowchart LR
    A["🚪 Onboarding<br/>User Created"] --> B["📄 Document Upload<br/>(erp_user_documents)"]
    B --> C["📅 Attendance Tracking<br/>(erp_attendance)"]
    C --> D["🏖️ Leave Requests<br/>(erp_leave_requests)"]
    D --> E["✈️ Air Tickets<br/>(erp_air_tickets)"]
    E --> F["💰 Payroll Run<br/>(erp_payroll_runs → erp_payslips)"]
    F --> G["🏁 Gratuity Settlement<br/>(erp_gratuity_settlements)"]
    G --> H["🚪 Offboarding"]

    D -.-> D1["Approved by Manager/HR"]
    F -.-> F1["Run monthly/periodic"]

    style A fill:#c8e6c9,stroke:#2e7d32
    style H fill:#ffcdd2,stroke:#c62828
    style D1 fill:#fff9c4,stroke:#f9a825
    style F1 fill:#fff9c4,stroke:#f9a825
```

**Entities involved:**
- `erp_users` — employee record
- `erp_user_documents` — passport, visa, ID, contracts
- `erp_attendance` — daily punches
- `erp_leave_requests` — leave with approval status
- `erp_annual_leave_entitlements` — yearly leave balance per user
- `erp_air_tickets` — flight bookings
- `erp_salary_structures` → `erp_employee_salaries` → `erp_payroll_runs` → `erp_payslips`
- `erp_gratuity_settlements` — end-of-service calculation

---

## 3. Sales-to-Cash Team Flow (CRM → Accounting)

Shows how sales and accounts teams interact across the order-to-cash cycle.

```mermaid
flowchart LR
    subgraph CRM ["CRM Module"]
        L["🎯 Lead<br/>Lead Owner (Sales Person)"]
        LQ["📋 Lead Quotation"]
    end

    subgraph Sales_ACCT ["Accounting — Sales"]
        C["👥 Customer<br/>customer_owner / sales_person / cs_agent"]
        Q["📄 Quotation"]
        SO["📦 Sale Order"]
        INV["🧾 Invoice"]
        PR["✅ Payment Received"]
    end

    L -->|"Qualified"| C
    LQ --> Q
    Q -->|"Accepted"| SO
    SO -->|"Billed"| INV
    INV -->|"Collected"| PR

    C -.->|"Owned By"| Owner["Customer Owner (erp_users)"]
    C -.->|"Sold By"| SP["Sales Person (erp_users)"]
    C -.->|"Serviced By"| CSA["CS Agent (erp_users)"]

    style CRM fill:#e8f5e9,stroke:#2e7d32
    style Sales_ACCT fill:#e3f2fd,stroke:#1565c0
```

**Team roles in process:**

| Step | Role | Module |
|------|------|--------|
| Lead qualification | Sales Person (role_id=3) | CRM |
| Quotation sent | Sales Person | Accounting |
| Sale Order approval | Sales Manager / Accounts | Accounting |
| Invoice generation | Accounts (role_id=5) | Accounting |
| Payment collection | Accounts | Accounting |

**FK columns:** `erp_customers.sales_person`, `erp_customers.customer_owner`, `erp_customers.cs_agent`, `erp_customers.assigned_to` → `erp_users.id`

---

## 4. Procure-to-Pay Team Flow (Accounting)

Shows how purchasing and accounts payable teams interact.

```mermaid
flowchart LR
    subgraph Purchasing ["Accounting — Purchases"]
        V["🏭 Vendor<br/>(erp_vendors)"]
        PO["📑 Purchase Order"]
        EXP["📊 Expense / Purchase"]
        PM["💸 Payment Made"]
    end

    V -->|"Request Quote"| PO
    PO -->|"Goods/Services Received"| EXP
    EXP -->|"Due for Payment"| PM

    PO -.->|"Created by"| Req["Requestor (any role)"]
    PO -.->|"Approved by"| Mgr["Manager / Budget Owner"]
    PM -.->|"Authorized by"| Acct["Accounts Team"]

    style Purchasing fill:#f3e5f5,stroke:#6a1b9a
```

---

## 5. Shipping Operations Flow

Shows the freight/logistics operations team workflow.

```mermaid
flowchart LR
    subgraph Master ["Shipping Master Data"]
        Port["⚓ Ports<br/>(erp_ports)"]
        Carrier["🚛 Carriers<br/>(erp_carriers)"]
        Shipper["📤 Shippers<br/>(erp_shippers)"]
        Consignee["📥 Consignees<br/>(erp_consignees)"]
        HS["🔢 HS Codes<br/>(erp_hscodes)"]
    end

    subgraph Ops ["Shipping Operations"]
        SA["📋 Shipping Advice<br/>(erp_shipping_advices)"]
        SS["📦 Shipping Stocks<br/>(erp_shipping_stocks)"]
        SI["🧾 Shipping Invoice<br/>(erp_shipping_invoices)"]
    end

    Master --> SA
    SA --> SS
    SA --> SI

    SA -.->|"Arranged by"| Coord["Operations Coordinator"]
    SA -.->|"Carrier assigned"| Carrier
    SA -.->|"From/To Ports"| Port

    style Master fill:#fff8e1,stroke:#f57f17
    style Ops fill:#e0f2f1,stroke:#004d40
```

**Team roles:**
- Operations Manager — oversees all shipping workflows
- Shipping Coordinator — creates shipping advices, coordinates carriers
- Accounts — generates shipping invoices, tracks payments

---

## 6. Approval Workflow Chain

Cross-module approval flows that different manpower roles participate in.

```mermaid
flowchart TD
    subgraph Leave["Leave Approval (HR)"]
        L1["👤 Employee submits leave request"]
        L2["📋 Manager approves"]
        L3["✅ HR confirms"]
        L4["✔️ Leave Granted"]
        L1 --> L2 --> L3 --> L4
    end

    subgraph PO["Purchase Approval (Accounting)"]
        P1["👤 Requestor creates PO"]
        P2["📋 Budget Owner approves"]
        P3["✅ Purchase Order finalized"]
        P1 --> P2 --> P3
    end

    subgraph Expense["Expense Claim (Accounting)"]
        E1["👤 Employee submits expense"]
        E2["📋 Manager reviews"]
        E3["✅ Accounts verifies & pays"]
        E1 --> E2 --> E3
    end

    subgraph InvoicePay["Invoice Payment (Accounting)"]
        I1["📄 Invoice received"]
        I2["📋 Operations confirms delivery"]
        I3["✅ Accounts processes payment"]
        I1 --> I2 --> I3
    end

    style Leave fill:#e8f5e9,stroke:#2e7d32
    style PO fill:#fff3e0,stroke:#e65100
    style Expense fill:#fce4ec,stroke:#c62828
    style InvoicePay fill:#e3f2fd,stroke:#1565c0
```

**Role-to-approval mapping:**

| Flow | Initiator | Approver 1 | Approver 2 |
|------|-----------|------------|------------|
| Leave | Employee (any) | Manager (SUPER_ADMIN / Dept Head) | HR |
| Purchase Order | Requestor (SALES/OPS) | Budget Owner (ACCOUNTS) | — |
| Expense Claim | Employee (any) | Manager | Accounts |
| Invoice Payment | Vendor / System | Operations | Accounts |

---

## Implementation Notes

### Data Source Summary

| Chart | Primary Tables | Key FK Columns |
|-------|---------------|----------------|
| Org Hierarchy | `erp_departments`, `erp_designations`, `erp_users`, `erp_roles` | `department_id`, `designation_id`, `role_id` |
| Employee Lifecycle | `erp_users`, `erp_attendance`, `erp_leave_requests`, `erp_payroll_runs`, `erp_payslips`, `erp_air_tickets`, `erp_gratuity_settlements` | `user_id` across all |
| Sales-to-Cash | `erp_customers`, `erp_quotations`, `erp_sale_orders`, `erp_invoices`, `erp_payments_received` | `sales_person`, `customer_owner`, `cs_agent`, `assigned_to` |
| Procure-to-Pay | `erp_vendors`, `erp_purchase_orders`, `erp_expenses`, `erp_payments_made` | `created_by` |
| Shipping Ops | `erp_shipping_advices`, `erp_shipping_invoices`, `erp_shipping_stocks` + master data | `carrier_id`, `port_id`, `shipper_id`, `consignee_id` |
| Approvals | Various `status` fields on leave, PO, expense, invoice tables | `status` (pending/approved/rejected) |

### Future Enhancement Ideas

- Add a dedicated `approval_flows` table for configurable multi-tier approval chains
- Introduce an `erp_employee_supervisors` table for flexible manager-subordinate mapping (currently implicit via hierarchy)
- Build org chart visualization directly into the dashboard (front-end with Mermaid.js) using existing repo/service data
- Surface approval bottlenecks in dashboard KPI widgets
