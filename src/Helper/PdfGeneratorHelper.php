<?php

declare(strict_types=1);

namespace App\Helper;

class PdfGeneratorHelper
{
    /**
     * Supported modules (matches gen_pdf.php and PdfHelper::MODULES).
     */
    private const SUPPORTED = [
        'quotations',
        'invoices',
        'sale_orders',
        'credit_notes',
        'payments_received',
        'recurring_invoices',
        'purchase_orders',
        'purchases',
        'debit_notes',
        'payments_made',
        'expenses',
    ];

    /**
     * Generate or ensure a PDF exists for the given module and record id.
     * Uses shell_exec to call gen_pdf.php in a separate process — avoids
     * exit() poisoning and session contamination from included PDF scripts.
     *
     * @return string Absolute path to the PDF file, empty string on failure.
     */
    public static function ensure(string $module, int $id): string
    {
        if (!in_array($module, self::SUPPORTED, true) || $id <= 0) {
            return '';
        }

        $path = PdfHelper::storageDirFor($module) . '/' . PdfHelper::filenameWithExt($id);

        if (is_file($path)) {
            return $path;
        }

        $genScript = dirname(__DIR__, 2) . '/dashboard/gen_pdf.php';
        if (!is_file($genScript)) {
            return '';
        }

        $phpBin = self::resolvePhpBinary();
        $cmd = $phpBin . ' ' . escapeshellarg($genScript)
            . ' --module=' . escapeshellarg($module)
            . ' --id=' . (int) $id
            . ' 2>&1';

        @shell_exec($cmd);

        return is_file($path) ? $path : '';
    }

    /**
     * Resolve the PHP CLI executable. PHP_BINARY is unreliable under the
     * Apache SAPI on Windows (it points to httpd.exe), which breaks the
     * shell_exec call that generates PDFs. Fall back to the real php.exe
     * derived from the extension_dir (XAMPP layout), then PHP_BINDIR, then PATH.
     */
    private static function resolvePhpBinary(): string
    {
        if (defined('PHP_BINARY')) {
            $base = strtolower(basename(PHP_BINARY));
            if (str_starts_with($base, 'php') && is_file(PHP_BINARY)) {
                return PHP_BINARY;
            }
        }

        foreach ([dirname((string) ini_get('extension_dir')), (string) (defined('PHP_BINDIR') ? PHP_BINDIR : '')] as $dir) {
            if ($dir === '' || $dir === '.') {
                continue;
            }
            foreach (['php.exe', 'php'] as $name) {
                $candidate = $dir . DIRECTORY_SEPARATOR . $name;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return 'php';
    }
}
