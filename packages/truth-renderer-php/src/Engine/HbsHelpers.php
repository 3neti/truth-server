<?php

namespace TruthRenderer\Engine;

final class HbsHelpers
{
    public static function upper($v): string
    {
        return strtoupper((string)$v);
    }

    public static function lower($v): string
    {
        return strtolower((string)$v);
    }

    public static function currency($v): string
    {
        $n = \TruthRenderer\Engine\HbsHelpers::toFloat($v);
        return '$' . number_format($n, 2);
    }

    public static function date($v, $fmt = 'Y-m-d'): string
    {
        $ts = strtotime((string)$v);
        return $ts ? date($fmt, $ts) : '';
    }

    public static function multiply($a, $b): string
    {
        $res = \TruthRenderer\Engine\HbsHelpers::toFloat($a) * \TruthRenderer\Engine\HbsHelpers::toFloat($b);
        return rtrim(rtrim(number_format($res, 10, '.', ''), '0'), '.') ?: '0';
    }

    public static function lineTotal($qty, $price): string
    {
        $res = \TruthRenderer\Engine\HbsHelpers::toFloat($qty) * \TruthRenderer\Engine\HbsHelpers::toFloat($price);
        return rtrim(rtrim(number_format($res, 10, '.', ''), '0'), '.') ?: '0';
    }

    public static function calcTotal($items): string
    {
        if (is_object($items) && isset($items->items) && is_iterable($items->items)) {
            $items = $items->items;
        }
        if (!is_iterable($items)) {
            return '0';
        }

        $sum = 0.0;
        foreach ($items as $row) {
            $q = \TruthRenderer\Engine\HbsHelpers::toFloat(\TruthRenderer\Engine\HbsHelpers::getField($row, 'qty'));
            $p = \TruthRenderer\Engine\HbsHelpers::toFloat(\TruthRenderer\Engine\HbsHelpers::getField($row, 'price'));
            $sum += $q * $p;
        }

        return rtrim(rtrim(number_format($sum, 10, '.', ''), '0'), '.') ?: '0';
    }

    public static function round2($v): string
    {
        return number_format(\TruthRenderer\Engine\HbsHelpers::toFloat($v), 2, '.', '');
    }

    public static function currencyISO($amount, $code = 'USD'): string
    {
        $map = ['USD' => '$', 'EUR' => '€', 'PHP' => '₱'];
        $sym = $map[$code] ?? '';
        return $sym . number_format(\TruthRenderer\Engine\HbsHelpers::toFloat($amount), 2);
    }

    public static function eq($a, $b): bool
    {
        return (string)$a === (string)$b;
    }

    /**
     * Block helper: {{#let value var="name"}}...{{/let}}
     * LightnCandy passes $options['hash'] for named args and $options['fn'] for inner block.
     */
    public static function let($value, array $options): string
    {
        $hash = $options['hash'] ?? [];
        $varName = array_key_first($hash) ?? 'value';
        $ctx = [$varName => $value];
        return (isset($options['fn']) && is_callable($options['fn'])) ? (string) $options['fn']($ctx) : '';
    }

    // ---------- internal utilities ----------

    public static function getField(mixed $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }
        if ($row instanceof \ArrayAccess) {
            return $row->offsetExists($key) ? $row[$key] : null;
        }
        if (is_object($row)) {
            return property_exists($row, $key) ? $row->$key : null;
        }
        return null;
    }

    public static function toFloat(mixed $v): float
    {
        if (is_int($v) || is_float($v)) return (float)$v;
        if (is_string($v) && is_numeric($v)) return (float)$v;
        return 0.0;
    }


    public static function groupBy($context, array $options): string
    {
        $hash = $options['hash'] ?? [];
        $groupKey = $hash['key'] ?? null;

        if (!$groupKey || !is_array($context)) {
            return '';
        }

        $grouped = [];

        foreach ($context as $item) {
            $key = \TruthRenderer\Engine\HbsHelpers::getField($item, $groupKey);
            $grouped[$key][] = $item;
        }

        $output = '';
        foreach ($grouped as $key => $groupItems) {
            $ctx = [
                'key'   => $key,
                'items' => $groupItems,
            ];
            $output .= (isset($options['fn']) && is_callable($options['fn']))
                ? $options['fn']($ctx)
                : '';
        }

        return $output;
    }

    // Inside HbsHelpers.php
    public static function inc($value, array $options = []): int
    {
        return (int)$value + 1;
    }

    public static function startsWith($string, $prefix): bool
    {
        return str_starts_with((string)$string, (string)$prefix);
    }

    public static function includes($string, $needle): bool
    {
        return str_contains((string)$string, (string)$needle);
    }
}
