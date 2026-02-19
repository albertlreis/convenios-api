<?php

namespace App\Support;

class PtBrNumberParser
{
    public static function parseDecimal(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $negative = str_contains($raw, '-');
        $normalized = preg_replace('/[^\d,.\-]/', '', $raw) ?? '';
        $normalized = str_replace('-', '', $normalized);
        if ($normalized === '') {
            return null;
        }

        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false) {
            $decimalSeparator = ',';
        } elseif ($lastDot !== false) {
            $decimalSeparator = '.';
        } else {
            $decimalSeparator = null;
        }

        $integerPart = $normalized;
        $fractionPart = '';
        if ($decimalSeparator !== null) {
            $separatorPos = strrpos($normalized, $decimalSeparator);
            $integerPart = substr($normalized, 0, $separatorPos);
            $fractionPart = substr($normalized, $separatorPos + 1);
        }

        $integerDigits = preg_replace('/\D/', '', $integerPart) ?? '';
        $fractionDigits = preg_replace('/\D/', '', $fractionPart) ?? '';

        if ($integerDigits === '' && $fractionDigits === '') {
            return null;
        }

        if ($integerDigits === '') {
            $integerDigits = '0';
        }

        $number = $integerDigits;
        if ($fractionDigits !== '') {
            $number .= '.'.$fractionDigits;
        }

        if ($negative && $number !== '0' && $number !== '0.0') {
            $number = '-'.$number;
        }

        if (! is_numeric($number)) {
            return null;
        }

        return number_format((float) $number, 2, '.', '');
    }

    public static function parseInteger(mixed $value): ?int
    {
        $decimal = self::parseDecimal($value);
        if ($decimal === null) {
            return null;
        }

        return (int) floor((float) $decimal);
    }
}
