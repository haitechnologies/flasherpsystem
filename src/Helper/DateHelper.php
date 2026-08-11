<?php

declare(strict_types=1);

namespace App\Helper;

class DateHelper
{
    public static function toDbDate(?string $dateStr): string
    {
        if ($dateStr === null || $dateStr === '') {
            return '';
        }
        return date('Y-m-d', strtotime(str_replace('/', '-', $dateStr)));
    }

    public static function toDisplayDate(?string $dateStr): string
    {
        if ($dateStr === null || $dateStr === '') {
            return '';
        }
        $ts = strtotime($dateStr);
        return $ts !== false ? date('d/m/Y', $ts) : '';
    }

    public static function toInputDate(?string $dateStr): string
    {
        if ($dateStr === null || $dateStr === '') {
            return '';
        }
        $ts = strtotime($dateStr);
        return $ts !== false ? date('d-m-Y', $ts) : '';
    }

    public static function toDbDateTime(?string $dateStr): string
    {
        if ($dateStr === null || $dateStr === '') {
            return '';
        }
        return date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $dateStr)));
    }

    public static function toDisplayDateTime(?string $dateStr): string
    {
        if ($dateStr === null || $dateStr === '') {
            return '';
        }
        $ts = strtotime($dateStr);
        return $ts !== false ? date('d/m/Y H:i', $ts) : '';
    }
}
