<?php

if (! function_exists('normalize_mobile_number')) {
    function normalize_mobile_number(?string $mobile): string
    {
        if ($mobile === null) {
            return '';
        }

        $normalized = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($normalized, '91') && strlen($normalized) > 10) {
            $normalized = substr($normalized, 2);
        }

        return strlen($normalized) > 10
            ? substr($normalized, -10)
            : $normalized;
    }
}
