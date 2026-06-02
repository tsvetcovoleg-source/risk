<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$applicationId = get_int_param('application_id');
$memo = null;

if ($applicationId && $pdo instanceof PDO) {
    $statement = $pdo->prepare(
        'SELECT cm.*, ca.application_number, ca.requested_amount, ca.currency, ca.requested_term_months, ca.status AS application_status,
                c.client_name, c.idno
         FROM credit_memos cm
         INNER JOIN credit_applications ca ON ca.id = cm.application_id
         INNER JOIN clients c ON c.id = ca.client_id
         WHERE cm.application_id = ? AND ca.deleted_at IS NULL
         LIMIT 1'
    );
    $statement->execute([$applicationId]);
    $memo = $statement->fetch() ?: null;
}
$sections = [
    'executive_summary' => 'Executive summary',
    'client_description' => 'Client description',
    'transaction_description' => 'Transaction description',
    'financial_analysis' => 'Financial analysis',
    'risk_analysis' => 'Risk analysis',
    'collateral_analysis' => 'Collateral analysis',
    'strengths' => 'Strengths',
    'weaknesses' => 'Weaknesses',
    'recommendation' => 'Recommendation',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Credit memo print | <?= e(APP_NAME) ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 32px; line-height: 1.45; }
        .toolbar { margin-bottom: 24px; }
        .btn { border: 1px solid #1f2937; background: #1f2937; color: #fff; padding: 8px 14px; border-radius: 6px; cursor: pointer; }
        .document { max-width: 960px; margin: 0 auto; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 16px; margin-bottom: 20px; }
        h1 { margin: 0 0 8px; font-size: 26px; }
        h2 { border-bottom: 1px solid #d1d5db; padding-bottom: 6px; margin-top: 24px; font-size: 18px; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 20px; margin: 18px 0; }
        .meta div { padding: 6px 0; }
        .label { color: #4b5563; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
        .value { font-weight: 700; }
        .section { page-break-inside: avoid; white-space: normal; }
        @media print { .toolbar { display: none; } body { margin: 18mm; } }
    </style>
</head>
<body>
<div class="toolbar"><button class="btn" onclick="window.print()">Print</button></div>
<div class="document">
<?php if (!$memo): ?>
    <h1>Credit memo not found</h1>
    <p>The requested credit memo does not exist.</p>
<?php else: ?>
    <div class="header">
        <div class="label">Internal analytical document</div>
        <h1>Credit memo</h1>
        <div><?= e($memo['application_number']) ?> · <?= e($memo['client_name']) ?></div>
    </div>
    <div class="meta">
        <?php foreach ([
            'Application number' => $memo['application_number'],
            'Client name' => $memo['client_name'],
            'IDNO' => $memo['idno'],
            'Requested amount' => format_amount($memo['requested_amount'], $memo['currency']),
            'Currency' => $memo['currency'],
            'Requested term' => ($memo['requested_term_months'] ?: '-') . ' months',
            'Application status' => $memo['application_status'],
            'Recommended decision' => memo_decision_label($memo['recommended_decision']),
            'Prepared at' => format_date($memo['prepared_at'], 'd.m.Y H:i'),
            'Updated at' => format_date($memo['updated_at'], 'd.m.Y H:i'),
        ] as $label => $value): ?>
            <div><div class="label"><?= e($label) ?></div><div class="value"><?= e($value) ?></div></div>
        <?php endforeach; ?>
    </div>
    <?php foreach ($sections as $field => $label): ?>
        <div class="section"><h2><?= e($label) ?></h2><div><?= format_memo_text($memo[$field] ?? '') ?></div></div>
    <?php endforeach; ?>
    <div class="section"><h2>Recommended decision</h2><div class="value"><?= e(memo_decision_label($memo['recommended_decision'])) ?></div></div>
<?php endif; ?>
</div>
</body>
</html>
