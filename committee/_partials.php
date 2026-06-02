<?php
function render_committee_decision_context(array $context, bool $full = true): void
{
    $application = $context['application'] ?? null;
    $client = $context['client'] ?? null;
    $memo = $context['credit_memo'] ?? null;
    $scoring = $context['scoring_result'] ?? null;
    $collateralSummary = $context['collateral_summary'] ?? [];
    $collateral = $context['collateral'] ?? [];
    $ratios = $context['latest_financial_ratios'] ?? null;
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Decision context</h2>
            <?php if ($full && $application && $client): ?>
                <h3 class="h6 mt-3">Application data</h3>
                <div class="row g-3 mb-3">
                    <?php foreach ([
                        'Application number' => $application['application_number'],
                        'Client name' => $client['client_name'],
                        'IDNO' => $client['idno'],
                        'Requested amount' => format_amount($application['requested_amount'], $application['currency']),
                        'Currency' => $application['currency'],
                        'Requested term' => ($application['requested_term_months'] ?: '-') . ' months',
                        'Credit product' => $application['credit_product'],
                        'Credit purpose' => $application['credit_purpose'],
                        'Repayment source' => $application['repayment_source'],
                        'Current application status' => $application['status'],
                    ] as $label => $value): ?>
                        <div class="col-md-4"><div class="border rounded p-2 h-100"><div class="text-secondary small"><?= e($label) ?></div><div class="fw-semibold"><?= e($value ?: '-') ?></div></div></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <h3 class="h6">Credit memo</h3>
                        <?php if ($memo): ?>
                            <div class="mb-2"><span class="text-secondary small">Recommended decision</span><br><span class="badge text-bg-<?= e(memo_decision_badge_class($memo['recommended_decision'])) ?>"><?= e(memo_decision_label($memo['recommended_decision'])) ?></span></div>
                            <div class="mb-2"><span class="text-secondary small">Prepared at</span><div class="fw-semibold"><?= e(format_date($memo['prepared_at'], 'd.m.Y H:i')) ?></div></div>
                            <?php if ($full): ?><div class="text-secondary small mb-2"><?= nl2br(e(mb_substr((string) ($memo['recommendation'] ?? ''), 0, 500))) ?></div><?php endif; ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('memos/view.php?application_id=' . $memo['application_id'])) ?>">Open credit memo</a>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">Credit memo has not been created yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <h3 class="h6">Scoring</h3>
                        <?php if ($scoring): ?>
                            <div class="row g-2">
                                <?php foreach (['Financial score' => 'financial_score', 'Non-financial score' => 'non_financial_score', 'Collateral score' => 'collateral_score', 'Final score' => 'final_score'] as $label => $field): ?>
                                    <div class="col-6"><span class="text-secondary small"><?= e($label) ?></span><div class="fw-semibold"><?= e(format_score($scoring[$field])) ?></div></div>
                                <?php endforeach; ?>
                                <div class="col-6"><span class="text-secondary small">Risk level</span><br><span class="badge text-bg-<?= e(risk_level_badge_class($scoring['risk_level'])) ?>"><?= e($scoring['risk_level']) ?></span></div>
                                <div class="col-6"><span class="text-secondary small">Expert override status</span><div class="fw-semibold"><?= e($scoring['expert_override'] ? 'Yes' : 'No') ?></div></div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">Scoring has not been calculated yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <h3 class="h6">Collateral</h3>
                        <?php if ($collateral): ?>
                            <div class="row g-2">
                                <div class="col-6"><span class="text-secondary small">Number of collateral items</span><div class="fw-semibold"><?= e(count($collateral)) ?></div></div>
                                <div class="col-6"><span class="text-secondary small">Total accepted value</span><div class="fw-semibold"><?= e(format_amount($collateralSummary['total_accepted_application_currency'] ?? null, $collateralSummary['application_currency'] ?? '')) ?></div></div>
                                <div class="col-6"><span class="text-secondary small">Collateral coverage ratio</span><div class="fw-semibold"><?= e(format_percent($collateralSummary['collateral_coverage_ratio'] ?? null)) ?></div></div>
                                <div class="col-6"><span class="text-secondary small">LTV</span><div class="fw-semibold"><?= e(format_percent($collateralSummary['ltv'] ?? null)) ?></div></div>
                            </div>
                            <?php if (!empty($collateralSummary['has_different_currencies'])): ?><div class="alert alert-warning mt-2 mb-0">Collateral includes different currencies. Manual review is required.</div><?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">No collateral has been recorded for this application.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <h3 class="h6">Financial ratios</h3>
                        <?php if ($ratios): ?>
                            <div class="table-responsive"><table class="table table-sm mb-0"><tbody>
                                <?php foreach ([
                                    'current_ratio' => ['Current ratio', false],
                                    'debt_to_equity' => ['Debt to equity', false],
                                    'debt_to_assets' => ['Debt to assets', false],
                                    'equity_ratio' => ['Equity ratio', false],
                                    'ebitda_margin' => ['EBITDA margin', true],
                                    'net_profit_margin' => ['Net profit margin', true],
                                    'interest_coverage_ratio' => ['Interest coverage ratio', false],
                                    'debt_service_coverage_ratio' => ['Simplified DSCR', false],
                                ] as $field => $definition): ?>
                                    <tr><td><?= e($definition[0]) ?></td><td class="text-end fw-semibold"><?= e(format_ratio($ratios[$field] ?? null, $definition[1])) ?></td></tr>
                                <?php endforeach; ?>
                            </tbody></table></div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">Financial ratios have not been calculated yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function committee_form_fields(array $values): void
{
    ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="committee_date">Committee date</label><input class="form-control" type="date" id="committee_date" name="committee_date" value="<?= e($values['committee_date'] ?? date('Y-m-d')) ?>" required></div>
        <div class="col-md-4"><label class="form-label" for="decision">Decision</label><select class="form-select" id="decision" name="decision" required><option value="">Select decision</option><?php foreach (committee_decision_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= selected_attr($values['decision'] ?? '', $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="approved_currency">Approved currency</label><select class="form-select" id="approved_currency" name="approved_currency"><option value="">Not applicable</option><?php foreach (['MDL', 'EUR', 'USD'] as $currency): ?><option value="<?= e($currency) ?>" <?= selected_attr($values['approved_currency'] ?? '', $currency) ?>><?= e($currency) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="approved_amount">Approved amount</label><input class="form-control" type="number" step="0.01" min="0" id="approved_amount" name="approved_amount" value="<?= e($values['approved_amount'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label" for="approved_term_months">Approved term months</label><input class="form-control" type="number" min="0" id="approved_term_months" name="approved_term_months" value="<?= e($values['approved_term_months'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label" for="conditions">Conditions</label><textarea class="form-control" id="conditions" name="conditions" rows="4"><?= e($values['conditions'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label" for="rejection_reason">Rejection reason</label><textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4"><?= e($values['rejection_reason'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label" for="decision_notes">Decision notes</label><textarea class="form-control" id="decision_notes" name="decision_notes" rows="4"><?= e($values['decision_notes'] ?? '') ?></textarea></div>
    </div>
    <?php
}
