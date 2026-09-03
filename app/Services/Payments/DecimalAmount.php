<?php

namespace App\Services\Payments;

final class DecimalAmount
{
    // order_transactions.amount is DECIMAL(10,2).
    private const MAX_MINOR_UNITS = 9999999999;

    /**
     * Parse an external, major-unit amount without using floating point.
     */
    public static function fromMajorUnits(
        $value,
        bool $allowNegative = false,
        bool $allowCroatianThousands = false
    ): ?int {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $amount = preg_replace('/[\s\x{00A0}]+/u', '', trim((string) $value));

        if ($amount === '' || strlen($amount) > 32) {
            return null;
        }

        $negative = false;

        if ($amount[0] === '-') {
            if (! $allowNegative) {
                return null;
            }

            $negative = true;
            $amount = substr($amount, 1);
        }

        if ($amount === '') {
            return null;
        }

        if (strpos($amount, ',') !== false) {
            if (substr_count($amount, ',') !== 1) {
                return null;
            }

            if (strpos($amount, '.') !== false) {
                if (! $allowCroatianThousands
                    || ! preg_match('/^\d{1,3}(?:\.\d{3})*,\d{1,2}$/D', $amount)) {
                    return null;
                }

                $amount = str_replace('.', '', $amount);
            }

            $amount = str_replace(',', '.', $amount);
        }

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $amount, $matches)) {
            return null;
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($matches[2] ?? '', 2, '0');
        $minor = ltrim($whole . $fraction, '0');
        $minor = $minor === '' ? '0' : $minor;

        if (strlen($minor) > strlen((string) self::MAX_MINOR_UNITS)
            || (strlen($minor) === strlen((string) self::MAX_MINOR_UNITS)
                && strcmp($minor, (string) self::MAX_MINOR_UNITS) > 0)) {
            return null;
        }

        $result = (int) $minor;

        return $negative ? -$result : $result;
    }

    /**
     * Convert the application's DECIMAL(15,4) values to cents, half-up.
     */
    public static function fromDatabase($value): ?int
    {
        $scaled = self::databaseScaleFour($value);

        return $scaled === null ? null : self::roundScaleFour($scaled);
    }

    public static function databaseDifference($left, $right): ?int
    {
        $leftScaled = self::databaseScaleFour($left);
        $rightScaled = self::databaseScaleFour($right);

        if ($leftScaled === null || $rightScaled === null) {
            return null;
        }

        return self::roundScaleFour($leftScaled - $rightScaled);
    }

    public static function format(int $minor): string
    {
        $negative = $minor < 0;
        $minor = abs($minor);

        return ($negative ? '-' : '')
            . intdiv($minor, 100)
            . '.'
            . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function databaseScaleFour($value): ?int
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $amount = trim((string) $value);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,4}))?$/D', $amount, $matches)) {
            return null;
        }

        $whole = ltrim($matches[2], '0');
        $whole = $whole === '' ? '0' : $whole;

        // Keep arithmetic comfortably inside a signed 64-bit integer and inside
        // the transaction table's amount range.
        if (strlen($whole) > 8) {
            return null;
        }

        $fraction = str_pad($matches[3] ?? '', 4, '0');
        $scaled = ((int) $whole * 10000) + (int) $fraction;

        return $matches[1] === '-' ? -$scaled : $scaled;
    }

    private static function roundScaleFour(int $scaled): ?int
    {
        $negative = $scaled < 0;
        $minor = intdiv(abs($scaled) + 50, 100);

        if ($minor > self::MAX_MINOR_UNITS) {
            return null;
        }

        return $negative ? -$minor : $minor;
    }
}
