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

function validate_date(?string $date): bool
{
    return is_valid_date($date);
}

function format_percent(float|int|string|null $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, 2, '.', ' ') . '%';
}

function parse_decimal(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $normalized = str_replace([' ', ','], ['', '.'], trim((string) $value));
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    return number_format((float) $normalized, 2, '.', '');
}

function balance_difference(float|int|string|null $totalAssets, float|int|string|null $totalLiabilitiesAndEquity): float
{
    return round((float) $totalAssets - (float) $totalLiabilitiesAndEquity, 2);
}

function check_income_statement_consistency(array $income): array
{
    $checks = [
        'gross_profit' => [
            'label' => 'gross_profit = revenue - cost_of_goods_sold',
            'expected' => (float) ($income['revenue'] ?? 0) - (float) ($income['cost_of_goods_sold'] ?? 0),
            'actual' => (float) ($income['gross_profit'] ?? 0),
        ],
        'ebitda' => [
            'label' => 'EBITDA = gross_profit - operating_expenses',
            'expected' => (float) ($income['gross_profit'] ?? 0) - (float) ($income['operating_expenses'] ?? 0),
            'actual' => (float) ($income['ebitda'] ?? 0),
        ],
        'ebit' => [
            'label' => 'EBIT = EBITDA - depreciation_amortization',
            'expected' => (float) ($income['ebitda'] ?? 0) - (float) ($income['depreciation_amortization'] ?? 0),
            'actual' => (float) ($income['ebit'] ?? 0),
        ],
        'profit_before_tax' => [
            'label' => 'profit_before_tax = EBIT - interest_expense',
            'expected' => (float) ($income['ebit'] ?? 0) - (float) ($income['interest_expense'] ?? 0),
            'actual' => (float) ($income['profit_before_tax'] ?? 0),
        ],
        'net_profit' => [
            'label' => 'net_profit = profit_before_tax - tax_expense',
            'expected' => (float) ($income['profit_before_tax'] ?? 0) - (float) ($income['tax_expense'] ?? 0),
            'actual' => (float) ($income['net_profit'] ?? 0),
        ],
    ];

    $issues = [];
    foreach ($checks as $field => $check) {
        $difference = round($check['actual'] - $check['expected'], 2);
        if (abs($difference) > 0.01) {
            $issues[$field] = $check + ['difference' => $difference];
        }
    }

    return $issues;
}

function safe_divide(mixed $numerator, mixed $denominator): ?float
{
    if ($denominator === null || $denominator === '' || (float) $denominator == 0.0) {
        return null;
    }

    return (float) ($numerator ?? 0) / (float) $denominator;
}

function calculate_financial_ratios(array $balance, array $income, ?array $previousIncome = null): array
{
    $totalDebt = (float) ($balance['short_term_debt'] ?? 0) + (float) ($balance['long_term_debt'] ?? 0);
    $quickAssets = (float) ($balance['cash_and_equivalents'] ?? 0) + (float) ($balance['accounts_receivable'] ?? 0);

    $ratios = [
        'current_ratio' => safe_divide($balance['total_current_assets'] ?? null, $balance['total_current_liabilities'] ?? null),
        'quick_ratio' => safe_divide($quickAssets, $balance['total_current_liabilities'] ?? null),
        'debt_to_equity' => safe_divide($totalDebt, $balance['equity'] ?? null),
        'debt_to_assets' => safe_divide($totalDebt, $balance['total_assets'] ?? null),
        'equity_ratio' => safe_divide($balance['equity'] ?? null, $balance['total_assets'] ?? null),
        'ebitda_margin' => ($margin = safe_divide($income['ebitda'] ?? null, $income['revenue'] ?? null)) === null ? null : $margin * 100,
        'net_profit_margin' => ($margin = safe_divide($income['net_profit'] ?? null, $income['revenue'] ?? null)) === null ? null : $margin * 100,
        'interest_coverage_ratio' => safe_divide($income['ebit'] ?? null, $income['interest_expense'] ?? null),
        // Simplified MVP DSCR: principal repayment schedule is not implemented yet.
        // Future formula: cash flow available for debt service / scheduled debt service.
        'debt_service_coverage_ratio' => safe_divide($income['ebitda'] ?? null, $income['interest_expense'] ?? null),
        'revenue_growth_percent' => null,
        'net_profit_growth_percent' => null,
    ];

    if ($previousIncome !== null) {
        $revenueGrowth = safe_divide(
            (float) ($income['revenue'] ?? 0) - (float) ($previousIncome['revenue'] ?? 0),
            $previousIncome['revenue'] ?? null
        );
        $ratios['revenue_growth_percent'] = $revenueGrowth === null ? null : $revenueGrowth * 100;

        $previousNetProfit = (float) ($previousIncome['net_profit'] ?? 0);
        $netProfitGrowth = safe_divide(
            (float) ($income['net_profit'] ?? 0) - $previousNetProfit,
            abs($previousNetProfit)
        );
        $ratios['net_profit_growth_percent'] = $netProfitGrowth === null ? null : $netProfitGrowth * 100;
    }

    return $ratios;
}

function interpret_ratio(string $ratioName, mixed $value, array $context = []): string
{
    $equity = isset($context['equity']) ? (float) $context['equity'] : null;

    if (in_array($ratioName, ['debt_to_equity'], true) && $equity !== null && $equity <= 0.0) {
        return 'negative or zero equity, manual review required';
    }

    if (in_array($ratioName, ['equity_ratio'], true) && $equity !== null && $equity < 0.0) {
        return 'negative equity, manual review required';
    }

    if ($value === null || $value === '') {
        return 'Not calculated';
    }

    $value = (float) $value;

    return match ($ratioName) {
        'current_ratio' => $value < 1.0 ? 'weak liquidity' : ($value <= 1.5 ? 'acceptable liquidity' : 'comfortable liquidity'),
        'quick_ratio' => $value < 0.7 ? 'weak quick liquidity' : ($value <= 1.0 ? 'acceptable quick liquidity' : 'comfortable quick liquidity'),
        'debt_to_equity' => $value < 1.0 ? 'low leverage' : ($value <= 2.0 ? 'moderate leverage' : 'high leverage'),
        'debt_to_assets' => $value < 0.3 ? 'low debt burden' : ($value <= 0.6 ? 'moderate debt burden' : 'high debt burden'),
        'equity_ratio' => $value < 0.2 ? 'weak capitalization' : ($value <= 0.4 ? 'acceptable capitalization' : 'strong capitalization'),
        'ebitda_margin' => $value < 5.0 ? 'weak operating profitability' : ($value <= 15.0 ? 'moderate operating profitability' : 'strong operating profitability'),
        'net_profit_margin' => $value < 2.0 ? 'weak net profitability' : ($value <= 10.0 ? 'moderate net profitability' : 'strong net profitability'),
        'interest_coverage_ratio' => $value < 1.5 ? 'weak interest coverage' : ($value <= 3.0 ? 'acceptable interest coverage' : 'comfortable interest coverage'),
        'debt_service_coverage_ratio' => $value < 1.0 ? 'weak coverage' : ($value <= 1.3 ? 'acceptable coverage' : 'comfortable coverage'),
        'revenue_growth_percent' => $value < 0.0 ? 'revenue declined versus previous period' : ($value == 0.0 ? 'revenue unchanged versus previous period' : 'revenue increased versus previous period'),
        'net_profit_growth_percent' => $value < 0.0 ? 'net profit declined versus previous period' : ($value == 0.0 ? 'net profit unchanged versus previous period' : 'net profit increased versus previous period'),
        default => 'No interpretation rule',
    };
}

function format_ratio(mixed $value, bool $isPercent = false): string
{
    if ($value === null || $value === '') {
        return 'N/A';
    }

    return number_format((float) $value, $isPercent ? 1 : 2, '.', ' ') . ($isPercent ? '%' : '');
}

function get_ratio_warnings(?array $balance, ?array $income, ?array $previousIncome = null): array
{
    $warnings = [];
    $balance = $balance ?? [];
    $income = $income ?? [];

    if (!$balance) {
        $warnings[] = 'Balance sheet data is missing; balance-based ratios may be unavailable.';
    }

    if (!$income) {
        $warnings[] = 'Income statement data is missing; profitability and coverage ratios may be unavailable.';
    }

    if ($balance && (float) ($balance['equity'] ?? 0) < 0.0) {
        $warnings[] = 'Negative equity detected; leverage and capitalization require manual review.';
    }

    if ($income && (float) ($income['revenue'] ?? 0) == 0.0) {
        $warnings[] = 'Revenue is zero; margin ratios and revenue growth may be unavailable.';
    }

    if ($income && (float) ($income['interest_expense'] ?? 0) == 0.0) {
        $warnings[] = 'Interest expense is zero; interest coverage and simplified DSCR are not calculated.';
    }

    if ($income && (float) ($income['ebit'] ?? 0) <= 0.0) {
        $warnings[] = 'EBIT is zero or negative; interest coverage appears weak and needs analyst review.';
    }

    if ($balance && abs(balance_difference($balance['total_assets'] ?? 0, $balance['total_liabilities_and_equity'] ?? 0)) > 0.01) {
        $warnings[] = 'Balance sheet does not balance; verify total assets and total liabilities plus equity.';
    }

    if ($income) {
        foreach (check_income_statement_consistency($income) as $issue) {
            $warnings[] = 'P&L control discrepancy: ' . $issue['label'] . ' (difference ' . format_amount($issue['difference'], '') . ').';
        }
    }

    if ($previousIncome === null) {
        $warnings[] = 'No previous period income statement found; growth ratios are not calculated.';
    } else {
        if ((float) ($previousIncome['revenue'] ?? 0) == 0.0) {
            $warnings[] = 'Previous period revenue is zero; revenue growth is not calculated.';
        }
        if ((float) ($previousIncome['net_profit'] ?? 0) == 0.0) {
            $warnings[] = 'Previous period net profit is zero; net profit growth is not calculated.';
        } elseif ((float) ($previousIncome['net_profit'] ?? 0) < 0.0) {
            $warnings[] = 'Previous period was loss-making; net profit growth requires careful manual interpretation.';
        }
    }

    return $warnings;
}

function ratio_definitions(): array
{
    return [
        'liquidity' => [
            'label' => 'Liquidity',
            'ratios' => [
                'current_ratio' => ['label' => 'Current ratio', 'formula' => 'total_current_assets / total_current_liabilities', 'percent' => false],
                'quick_ratio' => ['label' => 'Quick ratio', 'formula' => '(cash_and_equivalents + accounts_receivable) / total_current_liabilities', 'percent' => false],
            ],
        ],
        'leverage' => [
            'label' => 'Leverage',
            'ratios' => [
                'debt_to_equity' => ['label' => 'Debt to equity', 'formula' => '(short_term_debt + long_term_debt) / equity', 'percent' => false],
                'debt_to_assets' => ['label' => 'Debt to assets', 'formula' => '(short_term_debt + long_term_debt) / total_assets', 'percent' => false],
                'equity_ratio' => ['label' => 'Equity ratio', 'formula' => 'equity / total_assets', 'percent' => false],
            ],
        ],
        'profitability' => [
            'label' => 'Profitability',
            'ratios' => [
                'ebitda_margin' => ['label' => 'EBITDA margin', 'formula' => 'ebitda / revenue * 100', 'percent' => true],
                'net_profit_margin' => ['label' => 'Net profit margin', 'formula' => 'net_profit / revenue * 100', 'percent' => true],
            ],
        ],
        'coverage' => [
            'label' => 'Coverage',
            'ratios' => [
                'interest_coverage_ratio' => ['label' => 'Interest coverage ratio', 'formula' => 'ebit / interest_expense', 'percent' => false],
                'debt_service_coverage_ratio' => ['label' => 'Simplified DSCR', 'formula' => 'ebitda / interest_expense', 'percent' => false, 'comment' => 'Simplified MVP DSCR. Principal repayment schedule is not implemented yet; future formula should use cash flow available for debt service / scheduled debt service.'],
            ],
        ],
        'growth' => [
            'label' => 'Growth',
            'ratios' => [
                'revenue_growth_percent' => ['label' => 'Revenue growth percent', 'formula' => '(current_period_revenue - previous_period_revenue) / previous_period_revenue * 100', 'percent' => true],
                'net_profit_growth_percent' => ['label' => 'Net profit growth percent', 'formula' => '(current_period_net_profit - previous_period_net_profit) / ABS(previous_period_net_profit) * 100', 'percent' => true],
            ],
        ],
    ];
}

function ratio_columns(): array
{
    $columns = [];
    foreach (ratio_definitions() as $group) {
        foreach ($group['ratios'] as $key => $definition) {
            $columns[$key] = $definition;
        }
    }

    return $columns;
}

function format_currency_amount(float|int|string|null $amount, ?string $currency): string
{
    if ($amount === null || $amount === '') {
        return 'N/A';
    }

    return number_format((float) $amount, 2, '.', ' ') . ' ' . ($currency ?: '');
}

function calculate_collateral_summary(array $collateralItems, float|int|string|null $requestedAmount, ?string $applicationCurrency): array
{
    $requestedAmountValue = (float) ($requestedAmount ?? 0);
    $currency = (string) ($applicationCurrency ?? '');
    $totalsByCurrency = [];
    $totalEstimatedApplicationCurrency = 0.0;
    $totalAcceptedApplicationCurrency = 0.0;
    $hasDifferentCurrencies = false;

    foreach ($collateralItems as $item) {
        $itemCurrency = (string) ($item['currency'] ?? '');
        $estimated = (float) ($item['estimated_market_value'] ?? 0);
        $accepted = (float) ($item['accepted_collateral_value'] ?? 0);

        if (!isset($totalsByCurrency[$itemCurrency])) {
            $totalsByCurrency[$itemCurrency] = [
                'currency' => $itemCurrency,
                'estimated_market_value' => 0.0,
                'accepted_collateral_value' => 0.0,
                'items_count' => 0,
            ];
        }

        $totalsByCurrency[$itemCurrency]['estimated_market_value'] += $estimated;
        $totalsByCurrency[$itemCurrency]['accepted_collateral_value'] += $accepted;
        $totalsByCurrency[$itemCurrency]['items_count']++;

        if ($itemCurrency === $currency) {
            $totalEstimatedApplicationCurrency += $estimated;
            $totalAcceptedApplicationCurrency += $accepted;
        } else {
            $hasDifferentCurrencies = true;
        }
    }

    $coverageRatio = null;
    if ($requestedAmountValue > 0) {
        $coverageRatio = ($totalAcceptedApplicationCurrency / $requestedAmountValue) * 100;
    }

    $ltv = null;
    if ($requestedAmountValue > 0 && $totalAcceptedApplicationCurrency > 0) {
        $ltv = ($requestedAmountValue / $totalAcceptedApplicationCurrency) * 100;
    }

    $warnings = [];
    if ($hasDifferentCurrencies) {
        $warnings[] = 'Collateral includes different currencies. Manual review is required.';
    }
    if ($requestedAmountValue <= 0) {
        $warnings[] = 'Requested amount is zero or missing. Coverage ratios are not calculated.';
    }

    return [
        'requested_amount' => $requestedAmountValue,
        'application_currency' => $currency,
        'total_estimated_application_currency' => $totalEstimatedApplicationCurrency,
        'total_accepted_application_currency' => $totalAcceptedApplicationCurrency,
        'totals_by_currency' => array_values($totalsByCurrency),
        'has_different_currencies' => $hasDifferentCurrencies,
        'collateral_coverage_ratio' => $coverageRatio,
        'ltv' => $ltv,
        'warnings' => $warnings,
    ];
}

function interpret_collateral_coverage(float|int|string|null $coverageRatio): string
{
    if ($coverageRatio === null || $coverageRatio === '') {
        return 'Not calculated';
    }

    $value = (float) $coverageRatio;
    if ($value < 50) {
        return 'weak collateral coverage';
    }
    if ($value <= 100) {
        return 'partial collateral coverage';
    }
    if ($value <= 150) {
        return 'acceptable collateral coverage';
    }

    return 'strong collateral coverage';
}

function interpret_ltv(float|int|string|null $ltv): string
{
    if ($ltv === null || $ltv === '') {
        return 'Not calculated';
    }

    $value = (float) $ltv;
    if ($value > 100) {
        return 'high LTV';
    }
    if ($value >= 70) {
        return 'moderate LTV';
    }

    return 'conservative LTV';
}

function score_current_ratio($value): int
{
    if ($value === null || $value === '') return 3;
    $value = (float) $value;
    if ($value >= 1.5) return 1;
    if ($value >= 1.0) return 2;
    if ($value >= 0.8) return 3;
    if ($value >= 0.5) return 4;
    return 5;
}

function score_debt_to_equity($value, $equity = null): int
{
    if ($equity !== null && $equity !== '' && (float) $equity <= 0.0) return 5;
    if ($value === null || $value === '') return 3;
    $value = (float) $value;
    if ($value < 1.0) return 1;
    if ($value <= 2.0) return 2;
    if ($value <= 3.0) return 3;
    if ($value <= 5.0) return 4;
    return 5;
}

function score_debt_to_assets($value): int
{
    if ($value === null || $value === '') return 3;
    $value = (float) $value;
    if ($value < 0.3) return 1;
    if ($value <= 0.5) return 2;
    if ($value <= 0.7) return 3;
    if ($value <= 0.9) return 4;
    return 5;
}

function score_equity_ratio($value): int
{
    if ($value === null || $value === '') return 3;
    $value = (float) $value;
    if ($value >= 0.4) return 1;
    if ($value >= 0.25) return 2;
    if ($value >= 0.15) return 3;
    if ($value > 0.0) return 4;
    return 5;
}

function score_ebitda_margin($value): int
{
    if ($value === null || $value === '') return 3;
    $value = (float) $value;
    if ($value >= 20.0) return 1;
    if ($value >= 10.0) return 2;
    if ($value >= 5.0) return 3;
    if ($value >= 0.0) return 4;
    return 5;
}

function score_net_profit_margin($value): int
{
    if ($value === null || $value === '') return 3;
    $value = (float) $value;
    if ($value >= 10.0) return 1;
    if ($value >= 5.0) return 2;
    if ($value >= 2.0) return 3;
    if ($value >= 0.0) return 4;
    return 5;
}

function score_interest_coverage($value): int
{
    if ($value === null || $value === '') return 3;
    $value = (float) $value;
    if ($value >= 4.0) return 1;
    if ($value >= 2.5) return 2;
    if ($value >= 1.5) return 3;
    if ($value >= 1.0) return 4;
    return 5;
}

function score_dscr($value): int
{
    if ($value === null || $value === '') return 3;
    $value = (float) $value;
    if ($value >= 2.0) return 1;
    if ($value >= 1.5) return 2;
    if ($value >= 1.2) return 3;
    if ($value >= 1.0) return 4;
    return 5;
}

function score_growth_percent($value): int
{
    if ($value === null || $value === '') return 3;
    $value = (float) $value;
    if ($value >= 20.0) return 1;
    if ($value >= 5.0) return 2;
    if ($value >= -5.0) return 3;
    if ($value >= -20.0) return 4;
    return 5;
}

function scoring_financial_factor_definitions(): array
{
    return [
        'current_ratio' => ['label' => 'Current ratio', 'percent' => false, 'scorer' => 'score_current_ratio'],
        'debt_to_equity' => ['label' => 'Debt to equity', 'percent' => false, 'scorer' => 'score_debt_to_equity'],
        'debt_to_assets' => ['label' => 'Debt to assets', 'percent' => false, 'scorer' => 'score_debt_to_assets'],
        'equity_ratio' => ['label' => 'Equity ratio', 'percent' => false, 'scorer' => 'score_equity_ratio'],
        'ebitda_margin' => ['label' => 'EBITDA margin', 'percent' => true, 'scorer' => 'score_ebitda_margin'],
        'net_profit_margin' => ['label' => 'Net profit margin', 'percent' => true, 'scorer' => 'score_net_profit_margin'],
        'interest_coverage_ratio' => ['label' => 'Interest coverage ratio', 'percent' => false, 'scorer' => 'score_interest_coverage'],
        'debt_service_coverage_ratio' => ['label' => 'Simplified DSCR', 'percent' => false, 'scorer' => 'score_dscr'],
        'revenue_growth_percent' => ['label' => 'Revenue growth percent', 'percent' => true, 'scorer' => 'score_growth_percent'],
        'net_profit_growth_percent' => ['label' => 'Net profit growth percent', 'percent' => true, 'scorer' => 'score_growth_percent'],
    ];
}

function calculate_financial_score($ratios, $balance = null): array
{
    $details = [];
    $warnings = [];
    $points = [];
    $equity = is_array($balance) ? ($balance['equity'] ?? null) : null;

    if ($equity !== null && $equity !== '' && (float) $equity <= 0.0) {
        $warnings[] = 'Negative or zero equity detected; debt to equity receives 5 points and requires manual review.';
    }

    foreach (scoring_financial_factor_definitions() as $key => $definition) {
        $value = is_array($ratios) ? ($ratios[$key] ?? null) : null;
        if ($value === null || $value === '') {
            $warnings[] = $definition['label'] . ' is missing and receives neutral 3 points.';
        }
        $point = $key === 'debt_to_equity'
            ? score_debt_to_equity($value, $equity)
            : $definition['scorer']($value);
        $points[] = $point;
        $details[$key] = [
            'label' => $definition['label'],
            'value' => $value,
            'percent' => $definition['percent'],
            'points' => $point,
            'comment' => interpret_score_point($point),
        ];
    }

    return [
        'score' => round(array_sum($points) / max(count($points), 1), 2),
        'details' => $details,
        'warnings' => array_values(array_unique($warnings)),
    ];
}

function calculate_collateral_score($collateralSummary): array
{
    $summary = is_array($collateralSummary) ? $collateralSummary : [];
    $warnings = $summary['warnings'] ?? [];
    $coverageRatio = $summary['collateral_coverage_ratio'] ?? null;
    $sameCurrencyAccepted = (float) ($summary['total_accepted_application_currency'] ?? 0);
    $totalsByCurrency = $summary['totals_by_currency'] ?? [];
    $hasAnyCollateral = count($totalsByCurrency) > 0;
    $hasDifferentCurrencies = (bool) ($summary['has_different_currencies'] ?? false);

    if (!$hasAnyCollateral) {
        $score = 5;
        $warnings[] = 'No collateral is registered for this application.';
    } elseif ($sameCurrencyAccepted <= 0.0 && $hasDifferentCurrencies) {
        $score = 4;
        $warnings[] = 'Collateral exists only in currencies different from the application currency; manual interpretation is required.';
    } elseif ($coverageRatio === null) {
        $score = 5;
        $warnings[] = 'Collateral coverage ratio is not calculated because requested amount is zero or missing.';
    } else {
        $coverage = (float) $coverageRatio;
        if ($coverage >= 150.0) $score = 1;
        elseif ($coverage >= 100.0) $score = 2;
        elseif ($coverage >= 70.0) $score = 3;
        elseif ($coverage >= 30.0) $score = 4;
        else $score = 5;
    }

    if ($hasDifferentCurrencies && $sameCurrencyAccepted > 0.0) {
        $warnings[] = 'Collateral includes different currencies; score uses only accepted value in application currency.';
    }

    return [
        'score' => round((float) $score, 2),
        'coverage_ratio' => $coverageRatio,
        'ltv' => $summary['ltv'] ?? null,
        'warnings' => array_values(array_unique($warnings)),
    ];
}

function calculate_non_financial_score($factors): float
{
    $values = [];
    foreach ((array) $factors as $value) {
        if ($value === null || $value === '') continue;
        $intValue = (int) $value;
        if ($intValue >= 1 && $intValue <= 5) $values[] = $intValue;
    }

    if (!$values) return 3.00;
    return round(array_sum($values) / count($values), 2);
}

function calculate_final_score($financialScore, $nonFinancialScore, $collateralScore): float
{
    return round(((float) $financialScore * 0.50) + ((float) $nonFinancialScore * 0.25) + ((float) $collateralScore * 0.25), 2);
}

function determine_risk_level($finalScore): string
{
    $score = (float) $finalScore;
    if ($score <= 1.75) return 'low';
    if ($score <= 2.50) return 'moderate';
    if ($score <= 3.25) return 'medium';
    if ($score <= 4.00) return 'high';
    return 'very_high';
}

function interpret_score_point($point): string
{
    return match ((int) $point) {
        1 => 'very good',
        2 => 'good',
        3 => 'acceptable',
        4 => 'weak',
        5 => 'high risk',
        default => 'not assessed',
    };
}

function format_score($value): string
{
    if ($value === null || $value === '') return 'N/A';
    return number_format((float) $value, 2, '.', ' ');
}

function risk_level_badge_class(?string $riskLevel): string
{
    return match ($riskLevel) {
        'low' => 'success',
        'moderate' => 'info',
        'medium' => 'warning',
        'high' => 'danger',
        'very_high' => 'dark',
        default => 'secondary',
    };
}

function non_financial_factor_definitions(): array
{
    return [
        'business_reputation' => 'Business reputation',
        'management_quality' => 'Management quality',
        'market_position' => 'Market position',
        'industry_risk' => 'Industry risk',
        'transparency_quality' => 'Transparency quality',
        'relationship_history' => 'Relationship history',
    ];
}

function normalize_non_financial_factor(mixed $value): ?int
{
    if ($value === null || $value === '') return null;
    $intValue = filter_var($value, FILTER_VALIDATE_INT);
    return ($intValue !== false && $intValue >= 1 && $intValue <= 5) ? (int) $intValue : null;
}
