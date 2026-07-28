<?php

declare(strict_types=1);

include('admin_elements/admin_header.php');

$roleId = \App\Core\Session::roleId();
if ($roleId === 4) {
    header('Location: dashboard_operations.php');
    exit;
}
if ($roleId === 5) {
    header('Location: dashboard_accounting.php');
    exit;
}
if ($roleId === 6) {
    header('Location: dashboard_hr.php');
    exit;
}
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h1 class="h5 mb-0">Dashboard</h1>
            </div>
        </div>
    </div>
    <div class="content-inner">
        <div class="content">
            <div class="text-center mb-4">
                <h1>Flash ERP</h1>
            </div>

            <?php
            $dashboardSystems = [
                'shipping' => [
                    'label' => 'Shipping & Logistics System',
                    'desc'  => 'Smart system for managing shipping, logistics, and deliveries.',
                    'icon'  => 'ph-lifebuoy',
                    'color' => 'success',
                    'link'  => 'dashboard_shipping.php',
                    'modules' => ['shipping_advices', 'shipping_invoices', 'shipping_stocks', 'shipping_customers', 'hscodes', 'ports', 'carriers', 'consignees', 'shippers'],
                ],
                'accounting' => [
                    'label' => 'Accounting System',
                    'desc'  => 'Efficient system for managing business finance and accounting.',
                    'icon'  => 'ph-stack',
                    'color' => 'warning',
                    'link'  => 'dashboard_accounting.php',
                    'modules' => ['banks', 'customers', 'quotations', 'sale_orders', 'invoices', 'payments_received', 'credit_notes', 'vendors', 'expenses', 'purchase_orders', 'purchases', 'payments_made', 'debit_notes', 'journals', 'accounts'],
                ],
                'crm' => [
                    'label' => 'CRM',
                    'desc'  => 'Powerful CRM to manage leads, customers, and sales pipeline.',
                    'icon'  => 'ph-files',
                    'color' => 'primary',
                    'link'  => 'dashboard_crm.php',
                    'modules' => ['leads', 'lead_quotations'],
                ],
                'hr' => [
                    'label' => 'HR',
                    'desc'  => 'Streamlined HR system for employee records and payroll.',
                    'icon'  => 'ph-users',
                    'color' => 'indigo',
                    'link'  => 'dashboard_hr.php',
                    'modules' => ['departments', 'designations', 'attendance', 'leave_requests', 'leave_types', 'payroll_components', 'salary_structures', 'employee_salaries', 'payroll_runs', 'payslips', 'user_documents', 'users', 'report_hr'],
                ],
            ];

            $hasSystemAccess = function ($modules) {
                if (function_exists('has_full_access') && has_full_access()) return true;
                foreach ($modules as $module) {
                    if (function_exists('granted_') && granted_('view', $module)) return true;
                }
                return false;
            };
            ?>
            <div class="row">
                <?php foreach ($dashboardSystems as $system): ?>
                <?php if ($hasSystemAccess($system['modules'])): ?>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="d-inline-flex bg-<?php echo $system['color']; ?> bg-opacity-10 text-<?php echo $system['color']; ?> rounded-pill p-2 mb-3 mt-1">
                                <i class="<?php echo $system['icon']; ?> ph-2x m-1"></i>
                            </div>
                            <h5 class="card-title"><?php echo $system['label']; ?></h5>
                            <p class="mb-3"><?php echo $system['desc']; ?></p>
                            <a href="<?php echo $system['link']; ?>" class="btn btn-<?php echo $system['color']; ?> mb-1">Dashboard</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="live-status-container text-center mt-4">
                <div class="pulse-dots">
                    <span></span><span></span><span></span>
                </div>
                <p>System is live...</p>
            </div>

            <style>
                .pulse-dots {
                    display: inline-flex;
                    gap: 12px;
                    margin-bottom: 10px;
                }
                .pulse-dots span {
                    width: 16px;
                    height: 16px;
                    background-color: #007bff;
                    border-radius: 50%;
                    animation: pulse 1.4s infinite ease-in-out;
                }
                .pulse-dots span:nth-child(1) { animation-delay: 0s; }
                .pulse-dots span:nth-child(2) { animation-delay: 0.2s; }
                .pulse-dots span:nth-child(3) { animation-delay: 0.4s; }
                @keyframes pulse {
                    0%, 80%, 100% { transform: scale(0.8); opacity: 0.6; }
                    40% { transform: scale(1.2); opacity: 1; }
                }
            </style>
        </div>
        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>
<?php
include('admin_elements/admin_footer.php');
