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

function memo_decision_options(): array
{
    return [
        'approve' => 'Approve',
        'approve_with_conditions' => 'Approve with conditions',
        'reject' => 'Reject',
        'postpone' => 'Postpone',
        'request_additional_information' => 'Request additional information',
    ];
}

function is_valid_memo_decision(?string $decision): bool
{
    return $decision !== null && array_key_exists($decision, memo_decision_options());
}

function memo_decision_label(?string $decision): string
{
    if ($decision === null || $decision === '') {
        return '-';
    }

    return memo_decision_options()[$decision] ?? $decision;
}

function memo_decision_badge_class(?string $decision): string
{
    return match ($decision) {
        'approve' => 'success',
        'approve_with_conditions' => 'info',
        'reject' => 'danger',
        'postpone' => 'warning',
        'request_additional_information' => 'secondary',
        default => 'light',
    };
}

function get_application_full_context(PDO $pdo, $applicationId): array
{
    $applicationId = (int) $applicationId;

    $statement = $pdo->prepare(
        'SELECT ca.*, c.client_name, c.idno, c.legal_form, c.registration_date, c.activity_sector, c.caem_code,
                c.address, c.status AS client_status, c.notes AS client_notes
         FROM credit_applications ca
         INNER JOIN clients c ON c.id = ca.client_id
         WHERE ca.id = ? AND ca.deleted_at IS NULL AND c.deleted_at IS NULL'
    );
    $statement->execute([$applicationId]);
    $application = $statement->fetch() ?: null;

    if (!$application) {
        return [
            'application' => null,
            'client' => null,
            'related_parties' => [],
            'latest_financial_period' => null,
            'latest_balance_sheet' => null,
            'latest_income_statement' => null,
            'latest_financial_ratios' => null,
            'collateral' => [],
            'collateral_summary' => calculate_collateral_summary([], 0, null),
            'scoring_result' => null,
            'non_financial_factors' => null,
        ];
    }

    $client = [
        'id' => $application['client_id'],
        'client_name' => $application['client_name'],
        'idno' => $application['idno'],
        'legal_form' => $application['legal_form'],
        'registration_date' => $application['registration_date'],
        'activity_sector' => $application['activity_sector'],
        'caem_code' => $application['caem_code'],
        'address' => $application['address'],
        'status' => $application['client_status'],
        'notes' => $application['client_notes'],
    ];

    $statement = $pdo->prepare('SELECT * FROM client_related_parties WHERE client_id = ? AND deleted_at IS NULL ORDER BY is_beneficiary DESC, ownership_percent DESC, party_name ASC');
    $statement->execute([$client['id']]);
    $relatedParties = $statement->fetchAll();

    $statement = $pdo->prepare('SELECT * FROM financial_periods WHERE application_id = ? AND deleted_at IS NULL ORDER BY period_end_date DESC, id DESC LIMIT 1');
    $statement->execute([$applicationId]);
    $latestFinancialPeriod = $statement->fetch() ?: null;

    $latestBalanceSheet = null;
    $latestIncomeStatement = null;
    $latestFinancialRatios = null;

    if ($latestFinancialPeriod) {
        $statement = $pdo->prepare('SELECT * FROM financial_balance_sheet WHERE financial_period_id = ? LIMIT 1');
        $statement->execute([$latestFinancialPeriod['id']]);
        $latestBalanceSheet = $statement->fetch() ?: null;

        $statement = $pdo->prepare('SELECT * FROM financial_income_statement WHERE financial_period_id = ? LIMIT 1');
        $statement->execute([$latestFinancialPeriod['id']]);
        $latestIncomeStatement = $statement->fetch() ?: null;

        $statement = $pdo->prepare('SELECT * FROM financial_ratios WHERE application_id = ? AND financial_period_id = ? LIMIT 1');
        $statement->execute([$applicationId, $latestFinancialPeriod['id']]);
        $latestFinancialRatios = $statement->fetch() ?: null;
    }

    $statement = $pdo->prepare('SELECT * FROM collateral WHERE application_id = ? AND deleted_at IS NULL ORDER BY accepted_collateral_value DESC, estimated_market_value DESC, id DESC');
    $statement->execute([$applicationId]);
    $collateral = $statement->fetchAll();
    $collateralSummary = calculate_collateral_summary($collateral, $application['requested_amount'], $application['currency']);

    $statement = $pdo->prepare('SELECT * FROM scoring_results WHERE application_id = ? ORDER BY id DESC LIMIT 1');
    $statement->execute([$applicationId]);
    $scoringResult = $statement->fetch() ?: null;

    $nonFinancialFactors = null;
    if ($scoringResult) {
        try {
            $statement = $pdo->prepare('SELECT * FROM scoring_non_financial_factors WHERE scoring_result_id = ? ORDER BY id DESC LIMIT 1');
            $statement->execute([$scoringResult['id']]);
            $nonFinancialFactors = $statement->fetch() ?: null;
        } catch (PDOException) {
            $nonFinancialFactors = null;
        }
    }

    return [
        'application' => $application,
        'client' => $client,
        'related_parties' => $relatedParties,
        'latest_financial_period' => $latestFinancialPeriod,
        'latest_balance_sheet' => $latestBalanceSheet,
        'latest_income_statement' => $latestIncomeStatement,
        'latest_financial_ratios' => $latestFinancialRatios,
        'collateral' => $collateral,
        'collateral_summary' => $collateralSummary,
        'scoring_result' => $scoringResult,
        'non_financial_factors' => $nonFinancialFactors,
    ];
}

function generate_credit_memo_draft($context): array
{
    return [
        'executive_summary' => generate_executive_summary($context),
        'client_description' => generate_client_description($context),
        'transaction_description' => generate_transaction_description($context),
        'financial_analysis' => generate_financial_analysis($context),
        'risk_analysis' => generate_risk_analysis($context),
        'collateral_analysis' => generate_collateral_analysis($context),
        'strengths' => generate_strengths($context),
        'weaknesses' => generate_weaknesses($context),
        'recommendation' => generate_recommendation($context),
        'recommended_decision' => suggest_recommended_decision($context),
    ];
}

function memo_value(mixed $value, string $fallback = 'not recorded'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    return (string) $value;
}

function memo_amount(mixed $amount, ?string $currency = null): string
{
    return format_amount($amount, $currency ?: '');
}

function generate_executive_summary($context): string
{
    $application = $context['application'] ?? [];
    $client = $context['client'] ?? [];
    $scoring = $context['scoring_result'] ?? null;
    $collateral = $context['collateral'] ?? [];
    $lines = [];

    $lines[] = sprintf(
        'The application concerns a credit facility requested by %s in the amount of %s for a term of %s months. The stated purpose of the facility is %s. The application is currently at status %s.',
        memo_value($client['client_name'] ?? null, 'the client'),
        memo_amount($application['requested_amount'] ?? null, $application['currency'] ?? ''),
        memo_value($application['requested_term_months'] ?? null),
        memo_value($application['credit_purpose'] ?? null),
        memo_value($application['status'] ?? null)
    );

    if ($scoring) {
        $lines[] = sprintf('The preliminary scoring result indicates a %s risk level with a final score of %s.', memo_value($scoring['risk_level'] ?? null), format_score($scoring['final_score'] ?? null));
    } else {
        $lines[] = 'The scoring result has not been calculated yet.';
    }

    $lines[] = $collateral
        ? sprintf('Collateral has been recorded in the system for %d item(s).', count($collateral))
        : 'No collateral has been recorded for this application.';

    return implode("\n", $lines);
}

function generate_client_description($context): string
{
    $client = $context['client'] ?? [];
    $relatedParties = $context['related_parties'] ?? [];
    $lines = [];

    $lines[] = sprintf(
        'Client: %s. IDNO: %s. Legal form: %s. Registration date: %s. Activity sector: %s. CAEM code: %s. Address: %s. Client status: %s.',
        memo_value($client['client_name'] ?? null),
        memo_value($client['idno'] ?? null),
        memo_value($client['legal_form'] ?? null),
        format_date($client['registration_date'] ?? null),
        memo_value($client['activity_sector'] ?? null),
        memo_value($client['caem_code'] ?? null),
        memo_value($client['address'] ?? null),
        memo_value($client['status'] ?? null)
    );

    if ($relatedParties) {
        $lines[] = 'Related parties and beneficiaries recorded in the system:';
        foreach ($relatedParties as $party) {
            $beneficiary = !empty($party['is_beneficiary']) ? 'beneficiary' : 'not marked as beneficiary';
            $ownership = ($party['ownership_percent'] ?? null) !== null && $party['ownership_percent'] !== '' ? ', ownership ' . format_percent($party['ownership_percent']) : '';
            $lines[] = sprintf('- %s (%s, %s, %s%s).', memo_value($party['party_name'] ?? null), memo_value($party['party_type'] ?? null), memo_value($party['relationship_type'] ?? null), $beneficiary, $ownership);
        }
    } else {
        $lines[] = 'No related parties have been recorded in the system at this stage.';
    }

    return implode("\n", $lines);
}

function generate_transaction_description($context): string
{
    $application = $context['application'] ?? [];
    $lines = [];
    $lines[] = 'Requested amount: ' . memo_amount($application['requested_amount'] ?? null, $application['currency'] ?? '');
    $lines[] = 'Currency: ' . memo_value($application['currency'] ?? null);
    $lines[] = 'Requested term: ' . memo_value($application['requested_term_months'] ?? null) . ' months';
    $lines[] = 'Credit product: ' . memo_value($application['credit_product'] ?? null);
    $lines[] = 'Credit purpose: ' . memo_value($application['credit_purpose'] ?? null);
    $lines[] = 'Repayment source: ' . memo_value($application['repayment_source'] ?? null);
    $lines[] = 'Existing exposure amount: ' . memo_amount($application['existing_exposure_amount'] ?? null, $application['currency'] ?? '');
    $lines[] = 'Proposed total exposure amount: ' . memo_amount($application['proposed_total_exposure_amount'] ?? null, $application['currency'] ?? '');
    $lines[] = 'Priority: ' . memo_value($application['priority'] ?? null);
    if (!empty($application['notes'])) {
        $lines[] = 'Application notes: ' . $application['notes'];
    }

    return implode("\n", $lines);
}

function generate_financial_analysis($context): string
{
    $application = $context['application'] ?? [];
    $period = $context['latest_financial_period'] ?? null;
    $balance = $context['latest_balance_sheet'] ?? null;
    $income = $context['latest_income_statement'] ?? null;
    $ratios = $context['latest_financial_ratios'] ?? null;
    $currency = $application['currency'] ?? '';
    $lines = [];

    if (!$period) {
        return 'No financial statements have been recorded for this application.';
    }

    $lines[] = sprintf('Latest financial period: %s, ending on %s.', memo_value($period['period_label'] ?? null), format_date($period['period_end_date'] ?? null));

    if ($income) {
        $lines[] = 'Revenue: ' . memo_amount($income['revenue'] ?? null, $currency);
        $lines[] = 'EBITDA: ' . memo_amount($income['ebitda'] ?? null, $currency);
        $lines[] = 'Net profit: ' . memo_amount($income['net_profit'] ?? null, $currency);
    } else {
        $lines[] = 'Income statement data is not recorded for the latest financial period.';
    }

    if ($balance) {
        $lines[] = 'Total assets: ' . memo_amount($balance['total_assets'] ?? null, $currency);
        $lines[] = 'Equity: ' . memo_amount($balance['equity'] ?? null, $currency);
        $lines[] = 'Short-term debt: ' . memo_amount($balance['short_term_debt'] ?? null, $currency);
        $lines[] = 'Long-term debt: ' . memo_amount($balance['long_term_debt'] ?? null, $currency);
    } else {
        $lines[] = 'Balance sheet data is not recorded for the latest financial period.';
    }

    if ($ratios) {
        $definitions = [
            'current_ratio' => ['Current ratio', false],
            'debt_to_equity' => ['Debt to equity', false],
            'debt_to_assets' => ['Debt to assets', false],
            'equity_ratio' => ['Equity ratio', false],
            'ebitda_margin' => ['EBITDA margin', true],
            'net_profit_margin' => ['Net profit margin', true],
            'interest_coverage_ratio' => ['Interest coverage ratio', false],
            'debt_service_coverage_ratio' => ['Simplified DSCR', false],
            'revenue_growth_percent' => ['Revenue growth percent', true],
            'net_profit_growth_percent' => ['Net profit growth percent', true],
        ];
        $lines[] = 'Calculated financial ratios:';
        foreach ($definitions as $key => [$label, $isPercent]) {
            $lines[] = '- ' . $label . ': ' . format_ratio($ratios[$key] ?? null, $isPercent);
        }
    } else {
        $lines[] = 'Financial ratios have not been calculated for the latest financial period.';
    }

    return implode("\n", $lines);
}

function generate_collateral_analysis($context): string
{
    $application = $context['application'] ?? [];
    $collateral = $context['collateral'] ?? [];
    $summary = $context['collateral_summary'] ?? [];
    $currency = $application['currency'] ?? ($summary['application_currency'] ?? '');
    $lines = [];

    if (!$collateral) {
        return 'No collateral has been recorded for this application.';
    }

    $lines[] = sprintf('Collateral recorded: %d item(s).', count($collateral));
    $lines[] = 'Total estimated market value in application currency: ' . memo_amount($summary['total_estimated_application_currency'] ?? null, $currency);
    $lines[] = 'Total accepted collateral value in application currency: ' . memo_amount($summary['total_accepted_application_currency'] ?? null, $currency);
    $lines[] = 'Collateral coverage ratio: ' . format_percent($summary['collateral_coverage_ratio'] ?? null);
    $lines[] = 'LTV: ' . format_percent($summary['ltv'] ?? null);

    if (!empty($summary['has_different_currencies'])) {
        $lines[] = 'Collateral includes different currencies; automatic aggregation is limited and manual review is required.';
    }

    if (!empty($summary['totals_by_currency'])) {
        $currencies = array_map(static fn ($row) => ($row['currency'] ?? '-') . ' (' . (int) ($row['items_count'] ?? 0) . ' item(s))', $summary['totals_by_currency']);
        $lines[] = 'Collateral currencies: ' . implode(', ', $currencies) . '.';
    }

    $lines[] = 'Main collateral items:';
    foreach (array_slice($collateral, 0, 5) as $item) {
        $lines[] = sprintf(
            '- %s: %s; owner: %s; accepted value: %s; status: %s.',
            memo_value($item['collateral_type'] ?? null),
            memo_value($item['description'] ?? null),
            memo_value($item['owner_name'] ?? null),
            memo_amount($item['accepted_collateral_value'] ?? null, $item['currency'] ?? ''),
            memo_value($item['pledge_status'] ?? null)
        );
    }

    return implode("\n", $lines);
}

function generate_risk_analysis($context): string
{
    $period = $context['latest_financial_period'] ?? null;
    $balance = $context['latest_balance_sheet'] ?? null;
    $ratios = $context['latest_financial_ratios'] ?? null;
    $summary = $context['collateral_summary'] ?? [];
    $scoring = $context['scoring_result'] ?? null;
    $lines = [];

    if (!$period) {
        $lines[] = 'Data completeness risk: no financial statements have been recorded for this application.';
    }
    if ($period && !$ratios) {
        $lines[] = 'Analytical completeness risk: financial ratios have not been calculated for the latest financial period.';
    }
    if (($summary['collateral_coverage_ratio'] ?? null) !== null && (float) $summary['collateral_coverage_ratio'] < 100.0) {
        $lines[] = 'Collateral risk: collateral coverage is below 100% and should be reviewed manually.';
    }
    if ($balance && (float) ($balance['equity'] ?? 0) < 0.0) {
        $lines[] = 'Capitalization risk: equity is negative in the latest balance sheet.';
    }
    if ($ratios && ((isset($ratios['debt_to_assets']) && (float) $ratios['debt_to_assets'] > 0.6) || (isset($ratios['debt_to_equity']) && (float) $ratios['debt_to_equity'] > 2.0))) {
        $lines[] = 'Leverage risk: debt indicators suggest elevated debt burden.';
    }
    if ($ratios && ((isset($ratios['ebitda_margin']) && (float) $ratios['ebitda_margin'] < 5.0) || (isset($ratios['net_profit_margin']) && (float) $ratios['net_profit_margin'] < 2.0))) {
        $lines[] = 'Profitability risk: profitability margins are weak based on the latest calculated ratios.';
    }
    if (!empty($summary['has_different_currencies'])) {
        $lines[] = 'Collateral currency risk: collateral includes different currencies and requires manual interpretation.';
    }
    if ($scoring) {
        $lines[] = sprintf('Scoring risk level: %s, final score %s.', memo_value($scoring['risk_level'] ?? null), format_score($scoring['final_score'] ?? null));
        if (!empty($scoring['expert_override'])) {
            $lines[] = 'Expert override warning: the scoring result includes an expert override and should be reviewed together with the override rationale.';
        }
    } else {
        $lines[] = 'Scoring risk: the scoring result has not been calculated yet.';
    }

    if (!$lines) {
        $lines[] = 'The available information is insufficient for a complete automated risk summary; manual analytical assessment is required.';
    }

    return implode("\n", $lines);
}

function generate_strengths($context): string
{
    $balance = $context['latest_balance_sheet'] ?? null;
    $income = $context['latest_income_statement'] ?? null;
    $ratios = $context['latest_financial_ratios'] ?? null;
    $summary = $context['collateral_summary'] ?? [];
    $scoring = $context['scoring_result'] ?? null;
    $relatedParties = $context['related_parties'] ?? [];
    $strengths = [];

    if (($summary['collateral_coverage_ratio'] ?? null) !== null && (float) $summary['collateral_coverage_ratio'] >= 100.0) {
        $strengths[] = ((float) $summary['collateral_coverage_ratio'] >= 150.0 ? 'Strong' : 'Acceptable') . ' collateral coverage based on accepted collateral value in application currency.';
    }
    if ($scoring && in_array($scoring['risk_level'], ['low', 'moderate'], true)) {
        $strengths[] = 'Low or moderate risk level according to the latest scoring result.';
    }
    if ($balance && (float) ($balance['equity'] ?? 0) > 0.0) {
        $strengths[] = 'Positive equity recorded in the latest balance sheet.';
    }
    if ($income && (float) ($income['net_profit'] ?? 0) > 0.0) {
        $strengths[] = 'Positive profitability recorded in the latest income statement.';
    }
    if ($ratios && isset($ratios['revenue_growth_percent']) && (float) $ratios['revenue_growth_percent'] > 0.0) {
        $strengths[] = 'Revenue growth is positive versus the previous analyzed period.';
    }
    if ($relatedParties) {
        $strengths[] = 'Related parties and/or beneficiaries are recorded in the system, supporting transparency of the ownership and relationship structure.';
    }

    if (!$strengths) {
        return 'No major strengths were automatically identified based on the data currently recorded in the system. This section should be completed manually.';
    }

    return implode("\n", array_map(static fn ($item) => '- ' . $item, $strengths));
}

function generate_weaknesses($context): string
{
    $period = $context['latest_financial_period'] ?? null;
    $balance = $context['latest_balance_sheet'] ?? null;
    $income = $context['latest_income_statement'] ?? null;
    $ratios = $context['latest_financial_ratios'] ?? null;
    $summary = $context['collateral_summary'] ?? [];
    $scoring = $context['scoring_result'] ?? null;
    $collateral = $context['collateral'] ?? [];
    $weaknesses = [];

    if (!$period) {
        $weaknesses[] = 'Financial statements have not been recorded.';
    }
    if (!$scoring) {
        $weaknesses[] = 'Scoring has not been calculated.';
    }
    if (!$collateral) {
        $weaknesses[] = 'No collateral has been recorded.';
    }
    if (($summary['collateral_coverage_ratio'] ?? null) !== null && (float) $summary['collateral_coverage_ratio'] < 100.0) {
        $weaknesses[] = 'Collateral coverage is below 100%.';
    }
    if ($balance && (float) ($balance['equity'] ?? 0) < 0.0) {
        $weaknesses[] = 'Negative equity is recorded in the latest balance sheet.';
    }
    if ($ratios && ((isset($ratios['debt_to_assets']) && (float) $ratios['debt_to_assets'] > 0.6) || (isset($ratios['debt_to_equity']) && (float) $ratios['debt_to_equity'] > 2.0))) {
        $weaknesses[] = 'High leverage indicators are present.';
    }
    if ($ratios && isset($ratios['current_ratio']) && (float) $ratios['current_ratio'] < 1.0) {
        $weaknesses[] = 'Weak liquidity based on current ratio below 1.0.';
    }
    if ($income && (float) ($income['net_profit'] ?? 0) < 0.0) {
        $weaknesses[] = 'Negative net profit is recorded.';
    }
    if ($ratios && isset($ratios['revenue_growth_percent']) && (float) $ratios['revenue_growth_percent'] < 0.0) {
        $weaknesses[] = 'Revenue declined versus the previous analyzed period.';
    }
    if (!$period || !$ratios || !$scoring) {
        $weaknesses[] = 'Some analytical data is incomplete and requires manual review.';
    }

    if (!$weaknesses) {
        return 'No major weaknesses were automatically identified based on the data currently recorded in the system. This section should be reviewed manually.';
    }

    return implode("\n", array_map(static fn ($item) => '- ' . $item, array_values(array_unique($weaknesses))));
}

function generate_recommendation($context): string
{
    $period = $context['latest_financial_period'] ?? null;
    $summary = $context['collateral_summary'] ?? [];
    $scoring = $context['scoring_result'] ?? null;
    $coverage = $summary['collateral_coverage_ratio'] ?? null;

    if (!$period) {
        return 'A preliminary recommendation cannot be completed without financial information. The analyst should request or record financial statements before preparing a final recommendation.';
    }

    if (!$scoring) {
        return 'The recommendation requires completion of the scoring analysis. The analyst should review the application data, financial statements and collateral before finalizing this section.';
    }

    if (in_array($scoring['risk_level'], ['low', 'moderate'], true) && $coverage !== null && (float) $coverage >= 100.0) {
        return 'Based on the currently recorded data, the application may be considered for a positive recommendation, subject to manual verification of financial information, collateral documentation and any internal conditions required by policy.';
    }

    if ($scoring['risk_level'] === 'medium') {
        return 'The scoring result indicates medium risk. Additional manual analysis is recommended before formulating a final position, including review of repayment capacity, leverage, collateral enforceability and sector context.';
    }

    if (in_array($scoring['risk_level'], ['high', 'very_high'], true)) {
        return 'The scoring result indicates elevated risk. A cautious approach is recommended, and the analyst should consider requesting additional information, clarifications or risk mitigants before any positive recommendation.';
    }

    return 'The recommendation should be finalized manually after review of all financial, collateral and non-financial factors.';
}

function suggest_recommended_decision($context): string
{
    $period = $context['latest_financial_period'] ?? null;
    $balance = $context['latest_balance_sheet'] ?? null;
    $summary = $context['collateral_summary'] ?? [];
    $scoring = $context['scoring_result'] ?? null;
    $collateral = $context['collateral'] ?? [];
    $coverage = $summary['collateral_coverage_ratio'] ?? null;

    if ($balance && (float) ($balance['equity'] ?? 0) < 0.0 && !$collateral) {
        return 'reject';
    }
    if (!$period || !$scoring) {
        return 'request_additional_information';
    }
    if (in_array($scoring['risk_level'], ['low', 'moderate'], true) && $coverage !== null && (float) $coverage >= 100.0) {
        return 'approve';
    }
    if ($scoring['risk_level'] === 'medium') {
        return 'postpone';
    }
    if (in_array($scoring['risk_level'], ['high', 'very_high'], true)) {
        return 'request_additional_information';
    }

    return 'request_additional_information';
}

function format_memo_text($text): string
{
    return nl2br(e($text));
}

function committee_decision_options(): array
{
    return [
        'approved' => 'Approved',
        'approved_with_conditions' => 'Approved with conditions',
        'rejected' => 'Rejected',
        'postponed' => 'Postponed',
        'returned_for_revision' => 'Returned for revision',
    ];
}

function committee_vote_options(): array
{
    return [
        'for' => 'For',
        'against' => 'Against',
        'abstain' => 'Abstain',
        'conditional' => 'Conditional',
    ];
}

function is_valid_committee_decision(?string $decision): bool
{
    return $decision !== null && array_key_exists($decision, committee_decision_options());
}

function is_valid_committee_vote(?string $vote): bool
{
    return $vote !== null && array_key_exists($vote, committee_vote_options());
}

function format_decision_label($decision): string
{
    if ($decision === null || $decision === '') {
        return '-';
    }

    return committee_decision_options()[$decision] ?? (string) $decision;
}

function format_vote_label($vote): string
{
    if ($vote === null || $vote === '') {
        return '-';
    }

    return committee_vote_options()[$vote] ?? (string) $vote;
}

function committee_decision_badge_class(?string $decision): string
{
    return match ($decision) {
        'approved' => 'success',
        'approved_with_conditions' => 'info',
        'rejected' => 'danger',
        'postponed' => 'warning',
        'returned_for_revision' => 'secondary',
        default => 'light',
    };
}

function committee_vote_badge_class(?string $vote): string
{
    return match ($vote) {
        'for' => 'success',
        'against' => 'danger',
        'abstain' => 'secondary',
        'conditional' => 'info',
        default => 'light',
    };
}

function map_committee_decision_to_application_status($decision): string
{
    return match ($decision) {
        'approved' => 'approved',
        'approved_with_conditions' => 'approved_with_conditions',
        'rejected' => 'rejected',
        'postponed' => 'committee_review',
        'returned_for_revision' => 'risk_review',
        default => 'committee_review',
    };
}

function validate_committee_decision($data, $application): array
{
    $errors = [];
    $warnings = [];
    $decision = clean_input($data['decision'] ?? '');
    $committeeDate = clean_input($data['committee_date'] ?? '');
    $approvedAmount = $data['approved_amount'] ?? null;
    $approvedCurrency = clean_input($data['approved_currency'] ?? '');
    $approvedTerm = $data['approved_term_months'] ?? null;
    $conditions = clean_input($data['conditions'] ?? '');
    $rejectionReason = clean_input($data['rejection_reason'] ?? '');
    $decisionNotes = clean_input($data['decision_notes'] ?? '');

    if ($committeeDate === '') {
        $errors[] = 'Committee date is required.';
    } elseif (!is_valid_date($committeeDate)) {
        $errors[] = 'Committee date must be a valid date in YYYY-MM-DD format.';
    }

    if (!is_valid_committee_decision($decision)) {
        $errors[] = 'Committee decision has an invalid value.';
    }

    if ($approvedCurrency !== '' && !in_array($approvedCurrency, ['MDL', 'EUR', 'USD'], true)) {
        $errors[] = 'Approved currency has an invalid value.';
    }

    $amountValue = $approvedAmount === null || $approvedAmount === '' ? null : (float) $approvedAmount;
    $termValue = $approvedTerm === null || $approvedTerm === '' ? null : (int) $approvedTerm;

    if (in_array($decision, ['approved', 'approved_with_conditions'], true)) {
        if ($amountValue === null) {
            $errors[] = 'Approved amount is required for this decision.';
        } elseif ($amountValue <= 0) {
            $errors[] = 'Approved amount must be greater than zero.';
        }
        if ($approvedCurrency === '') {
            $errors[] = 'Approved currency is required for this decision.';
        }
        if ($termValue === null) {
            $errors[] = 'Approved term is required for this decision.';
        } elseif ($termValue <= 0) {
            $errors[] = 'Approved term must be greater than zero.';
        }
    }

    if ($decision === 'approved_with_conditions' && $conditions === '') {
        $errors[] = 'Conditions are required for approval with conditions.';
    }

    if ($decision === 'rejected' && $rejectionReason === '') {
        $errors[] = 'Rejection reason is required for rejected decisions.';
    }

    if ($decision === 'postponed' && $decisionNotes === '') {
        $warnings[] = 'Decision notes are recommended for postponed decisions.';
    }

    if ($decision === 'returned_for_revision' && $decisionNotes === '' && $conditions === '') {
        $warnings[] = 'Decision notes or conditions are recommended for returned-for-revision decisions.';
    }

    if ($amountValue !== null && isset($application['requested_amount']) && $amountValue > (float) $application['requested_amount']) {
        $warnings[] = 'Approved amount exceeds requested amount. Manual review is required.';
    }

    return ['errors' => $errors, 'warnings' => $warnings];
}

function get_committee_voting_summary($votes): array
{
    $summary = [
        'total' => 0,
        'for' => 0,
        'against' => 0,
        'abstain' => 0,
        'conditional' => 0,
        'majority_supportive' => false,
    ];

    foreach ((array) $votes as $voteRow) {
        $vote = is_array($voteRow) ? ($voteRow['vote'] ?? '') : (string) $voteRow;
        if (array_key_exists($vote, committee_vote_options())) {
            $summary[$vote]++;
            $summary['total']++;
        }
    }

    $summary['majority_supportive'] = ($summary['for'] + $summary['conditional']) > $summary['against'];

    return $summary;
}

function committee_decision_differs_from_memo($committeeDecision, $memoRecommendedDecision): bool
{
    if ($committeeDecision === null || $committeeDecision === '' || $memoRecommendedDecision === null || $memoRecommendedDecision === '') {
        return false;
    }

    $normalize = static function (string $decision): string {
        return match ($decision) {
            'approve' => 'approved',
            'approve_with_conditions' => 'approved_with_conditions',
            'reject' => 'rejected',
            'request_additional_information' => 'returned_for_revision',
            default => $decision,
        };
    };

    return $normalize((string) $committeeDecision) !== $normalize((string) $memoRecommendedDecision);
}

function get_committee_decision_context($pdo, $applicationId): array
{
    $context = get_application_full_context($pdo, $applicationId);
    $applicationId = (int) $applicationId;

    $statement = $pdo->prepare('SELECT * FROM credit_memos WHERE application_id = ? LIMIT 1');
    $statement->execute([$applicationId]);
    $creditMemo = $statement->fetch() ?: null;

    $committeeDecision = null;
    $committeeVotes = [];
    $statement = $pdo->prepare('SELECT * FROM committee_decisions WHERE application_id = ? LIMIT 1');
    $statement->execute([$applicationId]);
    $committeeDecision = $statement->fetch() ?: null;
    if ($committeeDecision) {
        $statement = $pdo->prepare('SELECT * FROM committee_votes WHERE committee_decision_id = ? ORDER BY created_at DESC, id DESC');
        $statement->execute([$committeeDecision['id']]);
        $committeeVotes = $statement->fetchAll();
    }

    return $context + [
        'credit_memo' => $creditMemo,
        'committee_decision' => $committeeDecision,
        'committee_votes' => $committeeVotes,
        'voting_summary' => get_committee_voting_summary($committeeVotes),
    ];
}
