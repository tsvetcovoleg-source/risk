<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'ratios';
$pageTitle = 'Financial ratios detail';
$financialPeriodId = get_int_param('financial_period_id');
$context = null;
$ratio = null;
$balance = null;
$income = null;
$previousIncome = null;

if ($financialPeriodId && $pdo instanceof PDO) {
    $statement = $pdo->prepare(
        'SELECT fp.*, ca.application_number, ca.requested_amount, ca.currency, ca.status, ca.client_id, c.client_name, c.idno
         FROM financial_periods fp
         INNER JOIN credit_applications ca ON ca.id = fp.application_id AND ca.deleted_at IS NULL
         INNER JOIN clients c ON c.id = ca.client_id
         WHERE fp.id = ? AND fp.deleted_at IS NULL'
    );
    $statement->execute([$financialPeriodId]);
    $context = $statement->fetch();

    if ($context) {
        $statement = $pdo->prepare('SELECT * FROM financial_ratios WHERE financial_period_id = ? LIMIT 1');
        $statement->execute([$financialPeriodId]);
        $ratio = $statement->fetch();

        $statement = $pdo->prepare('SELECT * FROM financial_balance_sheet WHERE financial_period_id = ?');
        $statement->execute([$financialPeriodId]);
        $balance = $statement->fetch() ?: [];

        $statement = $pdo->prepare('SELECT * FROM financial_income_statement WHERE financial_period_id = ?');
        $statement->execute([$financialPeriodId]);
        $income = $statement->fetch() ?: [];

        $statement = $pdo->prepare(
            'SELECT i.* FROM financial_periods fp
             INNER JOIN financial_income_statement i ON i.financial_period_id = fp.id
             WHERE fp.application_id = ? AND fp.deleted_at IS NULL AND fp.period_end_date < ?
             ORDER BY fp.period_end_date DESC, fp.id DESC LIMIT 1'
        );
        $statement->execute([$context['application_id'], $context['period_end_date']]);
        $previousIncome = $statement->fetch() ?: null;
    }
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$context) {
    render_error_page('Financial period not found', 'The requested financial period does not exist or was deleted.', url('ratios/index.php'), 'Back to financial ratios');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Financial ratios</p>
            <h1 class="h2 mb-1"><?= e($context['period_label']) ?></h1>
            <p class="text-secondary mb-0"><?= e($context['application_number']) ?> · <?= e($context['client_name']) ?></p>
        </div>
        <div class="align-self-lg-center d-flex gap-2 flex-wrap">
            <form method="post" action="<?= e(url('ratios/calculate.php')) ?>"><input type="hidden" name="financial_period_id" value="<?= e($context['id']) ?>"><button class="btn btn-primary" type="submit">Recalculate ratios</button></form>
            <a class="btn btn-outline-secondary" href="<?= e(url('financials/view_period.php?id=' . $context['id'])) ?>">Back to financial period</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $context['application_id'])) ?>">Back to application</a>
        </div>
    </div>
</section>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Application and client information</h2>
        <div class="row g-3">
            <?php foreach (['Application number' => $context['application_number'], 'Client name' => $context['client_name'], 'IDNO' => $context['idno'], 'Requested amount' => format_amount($context['requested_amount'], $context['currency']), 'Currency' => $context['currency'], 'Application status' => $context['status'], 'Period label' => $context['period_label'], 'Period end date' => format_date($context['period_end_date'])] as $label => $value): ?>
                <div class="col-md-3"><div class="text-secondary small"><?= e($label) ?></div><div class="fw-semibold"><?= e($value ?: '-') ?></div></div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3 d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('applications/view.php?id=' . $context['application_id'])) ?>">Open application</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('financials/view_period.php?id=' . $context['id'])) ?>">Open financial period</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('clients/view.php?id=' . $context['client_id'])) ?>">Open client</a>
        </div>
    </div>
</div>

<?php if (!$ratio): ?>
    <div class="alert alert-warning">Financial ratios have not been calculated yet. Use the Recalculate ratios button to create them.</div>
<?php else: ?>
    <?php foreach (ratio_definitions() as $group): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h5 mb-3"><?= e($group['label']) ?></h2>
                <?php if ($group['label'] === 'Coverage'): ?><p class="text-secondary small">Simplified DSCR for MVP uses EBITDA / interest expense because the principal repayment schedule is not implemented yet.</p><?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light"><tr><th>Ratio name</th><th>Formula</th><th>Calculated value</th><th>Interpretation</th><th>Warning / comment</th></tr></thead>
                        <tbody>
                            <?php foreach ($group['ratios'] as $key => $definition): ?>
                                <?php
                                $comment = $definition['comment'] ?? '';
                                if ($key === 'interest_coverage_ratio' && $income && (float) ($income['ebit'] ?? 0) <= 0.0) {
                                    $comment = trim($comment . ' EBIT is zero or negative; weak coverage warning.');
                                }
                                if ($key === 'net_profit_growth_percent' && $previousIncome && (float) ($previousIncome['net_profit'] ?? 0) < 0.0) {
                                    $comment = trim($comment . ' Previous period was loss-making; manual interpretation required.');
                                }
                                ?>
                                <tr>
                                    <td><?= e($definition['label']) ?></td>
                                    <td><code><?= e($definition['formula']) ?></code></td>
                                    <td><?= e(format_ratio($ratio[$key] ?? null, $definition['percent'])) ?></td>
                                    <td><?= e(interpret_ratio($key, $ratio[$key] ?? null, ['equity' => $balance['equity'] ?? null])) ?></td>
                                    <td><?= e($comment ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">General warnings</h2>
        <?php $warnings = get_ratio_warnings($balance, $income, $previousIncome); ?>
        <?php if (!$warnings): ?>
            <div class="alert alert-success mb-0">No calculation warnings were detected.</div>
        <?php else: ?>
            <div class="alert alert-warning mb-0"><ul class="mb-0"><?php foreach ($warnings as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Quick actions</h2>
        <div class="d-flex gap-2 flex-wrap">
            <form method="post" action="<?= e(url('ratios/calculate.php')) ?>"><input type="hidden" name="financial_period_id" value="<?= e($context['id']) ?>"><button class="btn btn-primary" type="submit">Recalculate ratios</button></form>
            <a class="btn btn-outline-secondary" href="<?= e(url('financials/view_period.php?id=' . $context['id'])) ?>">Back to financial period</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $context['application_id'])) ?>">Back to application</a>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
