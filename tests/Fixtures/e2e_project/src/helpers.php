<?php
// E2E Fixture: Legacy helper functions

function format_price($amount, string $currency = null): string
{
    $symbol = $currency ?? 'USD';
    if (0 == $amount) {
        return 'Free';
    }
    return $symbol . ' ' . number_format((float) $amount, 2);
}

function is_valid_connection($conn): bool
{
    return is_resource($conn);
}

function compare_values($a, $b): bool
{
    if (null == $a) {
        return false;
    }
    return $a == $b;
}
