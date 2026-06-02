<?php if (!empty($errors)): ?><div class="col-12"><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>
<?php if (!empty($application['application_number'])): ?><div class="col-md-4"><label class="form-label">Application number</label><input class="form-control" value="<?= e($application['application_number']) ?>" disabled></div><?php endif; ?>
<div class="col-md-<?= !empty($application['application_number']) ? '8' : '6' ?>">
    <label class="form-label" for="client_id">Client *</label>
    <?php if (!empty($fixedClient)): ?>
        <input type="hidden" name="client_id" value="<?= e($fixedClient['id']) ?>">
        <input class="form-control" value="<?= e($fixedClient['client_name'] . ' (ID ' . $fixedClient['id'] . ')') ?>" disabled>
    <?php else: ?>
        <select class="form-select" id="client_id" name="client_id" required>
            <option value="">Select client</option>
            <?php foreach ($clients as $clientOption): ?><option value="<?= e($clientOption['id']) ?>" <?= selected_attr($application['client_id'] ?? '', $clientOption['id']) ?>><?= e($clientOption['client_name']) ?><?= $clientOption['idno'] ? ' — ' . e($clientOption['idno']) : '' ?></option><?php endforeach; ?>
        </select>
    <?php endif; ?>
</div>
<div class="col-md-3"><label class="form-label" for="application_date">Application date *</label><input class="form-control" type="date" id="application_date" name="application_date" required value="<?= e($application['application_date'] ?? date('Y-m-d')) ?>"></div>
<div class="col-md-3"><label class="form-label" for="requested_amount">Requested amount *</label><input class="form-control" type="number" min="0.01" step="0.01" id="requested_amount" name="requested_amount" required value="<?= e($application['requested_amount'] ?? '') ?>"></div>
<div class="col-md-2"><label class="form-label" for="currency">Currency *</label><select class="form-select" id="currency" name="currency"><?php foreach ($currencies as $currency): ?><option value="<?= e($currency) ?>" <?= selected_attr($application['currency'] ?? 'MDL', $currency) ?>><?= e($currency) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label" for="requested_term_months">Requested term, months *</label><input class="form-control" type="number" min="1" step="1" id="requested_term_months" name="requested_term_months" required value="<?= e($application['requested_term_months'] ?? '') ?>"></div>
<div class="col-md-3"><label class="form-label" for="interest_rate">Annual interest rate (%)</label><input class="form-control" type="number" min="0" max="100" step="0.0001" id="interest_rate" name="interest_rate" value="<?= e($application['interest_rate'] ?? '') ?>"><div class="form-text">Used to calculate the estimated annual debt service amount.</div></div>
<div class="col-md-3"><label class="form-label" for="estimated_annual_debt_service">Estimated annual debt service</label><input class="form-control" id="estimated_annual_debt_service" value="<?= e(($application['annual_debt_service_amount'] ?? null) === null || ($application['annual_debt_service_amount'] ?? '') === '' ? 'N/A' : format_amount($application['annual_debt_service_amount'], $application['currency'] ?? 'MDL')) ?>" readonly><div class="form-text">Preview only. The saved value is recalculated on the server.</div></div>
<div class="col-md-4"><label class="form-label" for="credit_product">Credit product</label><input class="form-control" id="credit_product" name="credit_product" value="<?= e($application['credit_product'] ?? '') ?>"></div>
<div class="col-md-3"><label class="form-label" for="existing_exposure_amount">Existing exposure</label><input class="form-control" type="number" min="0" step="0.01" id="existing_exposure_amount" name="existing_exposure_amount" value="<?= e($application['existing_exposure_amount'] ?? '0.00') ?>"></div>
<div class="col-md-3"><label class="form-label" for="proposed_total_exposure_amount">Proposed total exposure</label><input class="form-control" type="number" min="0" step="0.01" id="proposed_total_exposure_amount" name="proposed_total_exposure_amount" value="<?= e($application['proposed_total_exposure_amount'] ?? '0.00') ?>"></div>
<div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= selected_attr($application['status'] ?? 'draft', $status) ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label" for="priority">Priority</label><select class="form-select" id="priority" name="priority"><?php foreach ($priorities as $priority): ?><option value="<?= e($priority) ?>" <?= selected_attr($application['priority'] ?? 'normal', $priority) ?>><?= e($priority) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label" for="credit_purpose">Credit purpose *</label><textarea class="form-control" id="credit_purpose" name="credit_purpose" rows="2" required><?= e($application['credit_purpose'] ?? '') ?></textarea></div>
<div class="col-12"><label class="form-label" for="repayment_source">Repayment source</label><textarea class="form-control" id="repayment_source" name="repayment_source" rows="2"><?= e($application['repayment_source'] ?? '') ?></textarea></div>
<div class="col-12"><label class="form-label" for="notes">Notes</label><textarea class="form-control" id="notes" name="notes" rows="3"><?= e($application['notes'] ?? '') ?></textarea></div>

<script>
(function applicationDebtServiceCalculator() {
    const amountInput = document.getElementById('requested_amount');
    const termInput = document.getElementById('requested_term_months');
    const rateInput = document.getElementById('interest_rate');
    const currencyInput = document.getElementById('currency');
    const output = document.getElementById('estimated_annual_debt_service');

    if (!amountInput || !termInput || !rateInput || !output) {
        return;
    }

    function parseNumber(value) {
        if (value === null || value === undefined || String(value).trim() === '') {
            return null;
        }
        const number = Number(String(value).replace(/\s/g, '').replace(',', '.'));
        return Number.isFinite(number) ? number : null;
    }

    function formatAmount(value, currency) {
        return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/,/g, ' ') + ' ' + currency;
    }

    function recalculate() {
        const principal = parseNumber(amountInput.value);
        const termMonths = parseInt(termInput.value, 10);
        const annualRate = parseNumber(rateInput.value);
        const currency = currencyInput ? currencyInput.value : 'MDL';

        if (!principal || principal <= 0 || !termMonths || termMonths <= 0 || annualRate === null || annualRate < 0) {
            output.value = 'N/A';
            return;
        }

        let monthlyPayment;
        if (annualRate === 0) {
            monthlyPayment = principal / termMonths;
        } else {
            const monthlyRate = annualRate / 100 / 12;
            const denominator = 1 - Math.pow(1 + monthlyRate, -termMonths);
            if (denominator === 0) {
                output.value = 'N/A';
                return;
            }
            monthlyPayment = principal * monthlyRate / denominator;
        }

        monthlyPayment = Math.round(monthlyPayment * 100) / 100;
        output.value = formatAmount(monthlyPayment * 12, currency);
    }

    [amountInput, termInput, rateInput, currencyInput].forEach(function (input) {
        if (input) {
            input.addEventListener('input', recalculate);
            input.addEventListener('change', recalculate);
        }
    });

    recalculate();
})();
</script>
