/**
 * Datepicker Configuration
 * Extracted from admin_header.php
 * Manages all jQuery UI datepicker initializations across the dashboard
 */

(function($) {
    $(function() {
        // === INVOICE MODULE ===
        $("#invoice_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === CUSTOMERS MODULE ===
        $("#license_expiry").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === JOBS MODULE ===
        $("#job_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        $("#etd").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        $("#eta").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        $("#vessel_departure_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        $("#flight_departure_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        $("#job_completion_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === PAYMENT TRACKING ===
        $("#payment_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === EXPENSE MODULE ===
        $("#expense_date").datepicker({
            dateFormat: 'dd-mm-yy',
            maxDate: '0',
            changeMonth: true,
            changeYear: true
        });

        // === SALES & PURCHASE ORDERS ===
        $("#sale_order_date").datepicker({
            dateFormat: 'dd-mm-yy',
            maxDate: '0',
            changeMonth: true,
            changeYear: true
        });

        $("#purchase_order_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        $("#purchase_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === JOURNAL/ACCOUNTING ===
        $("#journal_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === DATE RANGES ===
        $("#start_date").datepicker({
            dateFormat: 'dd-mm-yy',
            minDate: '0',
            changeMonth: true,
            changeYear: true
        });

        $("#end_date").datepicker({
            dateFormat: 'dd-mm-yy',
            minDate: '0',
            changeMonth: true,
            changeYear: true
        });

        $("#due_date").datepicker({
            dateFormat: 'dd-mm-yy',
            minDate: '0',
            changeMonth: true,
            changeYear: true
        });

        $("#date_from").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        $("#date_to").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === CRM MODULE ===
        $("#contacted_date").datepicker({
            dateFormat: 'dd-mm-yy',
            maxDate: '0',
            changeMonth: true,
            changeYear: true
        });

        // === JOB COMPLETION RANGES ===
        $("#job_completion_date_from").datepicker({
            dateFormat: 'dd-mm-yy',
            maxDate: '0',
            changeMonth: true,
            changeYear: true
        });

        $("#job_completion_date_to").datepicker({
            dateFormat: 'dd-mm-yy',
            maxDate: '0',
            changeMonth: true,
            changeYear: true
        });

        // === DOCUMENT DATES ===
        $("#issued_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        $("#expiry_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === QUOTATION DATES ===
        $("#quotation_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === CREDIT NOTE DATES ===
        $("#credit_note_date").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === USER DOCUMENT DATES (Employee Profile) ===
        $("#doc-issued").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        $("#doc-expiry").datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        // === SHIPMENT DATES ===
        $("#expected_shipment_date").datepicker({
            dateFormat: 'dd-mm-yy',
            minDate: '0',
            changeMonth: true,
            changeYear: true
        });

        // === CASH MODULE ===
        $("#cash_date_time").datetimepicker({
            enableTime: true,
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true,
            yearRange: '2023:2025'
        });
    });
})(jQuery);
