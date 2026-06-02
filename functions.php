<?php
/**
 * Shared helper functions used by pages and layout templates.
 */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return url($path) . '?v=' . rawurlencode(APP_ASSET_VERSION);
}

function is_active_menu(string $section): string
{
    global $currentSection;

    return ($currentSection ?? 'dashboard') === $section ? 'active' : '';
}

function format_date(?string $date, string $format = 'd.m.Y'): string
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    return $timestamp ? date($format, $timestamp) : '-';
}

function format_amount(float|int|string|null $amount, string $currency = 'MDL'): string
{
    if ($amount === null || $amount === '') {
        return '-';
    }

    return number_format((float) $amount, 2, '.', ' ') . ' ' . $currency;
}
