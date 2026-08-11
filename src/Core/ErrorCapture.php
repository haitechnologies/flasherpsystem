<?php

declare(strict_types=1);

namespace App\Core;

final class ErrorCapture
{
    public static function record(
        string $message,
        string $severity = 'ERROR',
        ?string $file = null,
        ?int $line = null,
        array $context = []
    ): void {
        if ($file === null || $line === null) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $trace[1] ?? [];
            $file = $file ?? (string)($caller['file'] ?? __FILE__);
            $line = $line ?? (int)($caller['line'] ?? __LINE__);
        }

        if (function_exists('log_error')) {
            log_error($message, $severity, $file, $line, $context);
            return;
        }

        if (isset($GLOBALS['frontendLogger']) && is_object($GLOBALS['frontendLogger'])) {
            if ($severity === 'WARNING' && method_exists($GLOBALS['frontendLogger'], 'warning')) {
                $GLOBALS['frontendLogger']->warning($message, $context, $file, $line);
            } else {
                $GLOBALS['frontendLogger']->error($message, $context, $file, $line);
            }
            return;
        }

        $suffix = $context !== [] ? ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        error_log("[$file:$line] " . $message . $suffix);
    }
}
