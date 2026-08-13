<?php

declare(strict_types=1);

namespace App\Helper;

class PdfHelper
{
    /**
     * Canonical salt shared by every PDF generator and the send-email
     * attachment flow. Changing it invalidates all previously saved filenames.
     */
    public const SALT = '}#f4ga~g%7hjg4&jokho!bj30ab-wi=6gia^7-$^R9F|GaK5Jzxs#E6WT;IOJN';

    /**
     * Map module slug -> PDF generator page (singular filename) and the
     * request parameter that carries the record id.
     */
    public const MODULES = [
        'quotations'         => ['page' => 'pdf_quotation.php',          'id_param' => 'id'],
        'invoices'           => ['page' => 'pdf_invoice.php',            'id_param' => 'id'],
        'sale_orders'        => ['page' => 'pdf_sale_order.php',         'id_param' => 'id'],
        'credit_notes'       => ['page' => 'pdf_credit_note.php',        'id_param' => 'credit_note_id'],
        'payments_received'  => ['page' => 'pdf_payment_received.php',   'id_param' => 'payment_received_id'],
        'recurring_invoices' => ['page' => 'pdf_recurring_invoice.php',  'id_param' => 'recurring_invoice_id'],
        'purchase_orders'    => ['page' => 'pdf_purchase_order.php',     'id_param' => 'id'],
        'purchases'          => ['page' => 'pdf_purchase.php',           'id_param' => 'id'],
        'debit_notes'        => ['page' => 'pdf_debit_note.php',         'id_param' => 'id'],
        'payments_made'      => ['page' => 'pdf_payment_made.php',       'id_param' => 'id'],
        'expenses'           => ['page' => 'pdf_expense.php',            'id_param' => 'id'],
    ];

    public static function filename(int $id): string
    {
        return hash('sha256', self::SALT . $id);
    }

    public static function filenameWithExt(int $id): string
    {
        return self::filename($id) . '.pdf';
    }

    public static function pageFor(string $module): string
    {
        return self::MODULES[$module]['page'] ?? '';
    }

    public static function idParamFor(string $module): string
    {
        return self::MODULES[$module]['id_param'] ?? 'id';
    }

    public static function storageDirFor(string $module): string
    {
        return dirname(__DIR__, 2) . '/pdfs_' . preg_replace('/_$/', '', $module);
    }
}
