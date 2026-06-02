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

function clean_input(mixed $value): string
{
    return trim((string) $value);
}

function nullable_input(mixed $value): ?string
{
    $cleaned = clean_input($value);

    return $cleaned === '' ? null : $cleaned;
}

function post_value(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function get_int_param(string $key): ?int
{
    $value = $_GET[$key] ?? null;

    if ($value === null || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        return null;
    }

    return (int) $value;
}

function post_int(string $key): ?int
{
    $value = $_POST[$key] ?? null;

    if ($value === null || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        return null;
    }

    return (int) $value;
}

function is_valid_date(?string $date): bool
{
    if ($date === null || $date === '') {
        return true;
    }

    $parsed = DateTime::createFromFormat('Y-m-d', $date);

    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function selected_attr(mixed $actual, mixed $expected): string
{
    return (string) $actual === (string) $expected ? 'selected' : '';
}

function checked_attr(mixed $value): string
{
    return (bool) $value ? 'checked' : '';
}

function render_error_page(string $title, string $message, string $backUrl, string $backLabel = 'Back'): void
{
    ?>
    <section class="page-heading mb-4">
        <p class="eyebrow mb-2">Error</p>
        <h1 class="h2 mb-3"><?= e($title) ?></h1>
        <p class="text-secondary mb-0"><?= e($message) ?></p>
    </section>
    <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
    <?php
}

function log_action(PDO $pdo, string $action, string $entityType, int $entityId, mixed $oldValue = null, mixed $newValue = null): void
{
    try {
        $statement = $pdo->prepare(
            'INSERT INTO audit_logs (action, entity_type, entity_id, old_value, new_value, ip_address, user_agent)
             VALUES (:action, :entity_type, :entity_id, :old_value, :new_value, :ip_address, :user_agent)'
        );
        $statement->execute([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_value' => $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'new_value' => $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        ]);
    } catch (PDOException) {
        // Audit logging is useful but should not block core MVP operations.
    }
}
