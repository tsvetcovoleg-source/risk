<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('scoring/index.php'));
}
$applicationId = post_int('application_id');
if (!$applicationId || !($pdo instanceof PDO)) {
    redirect(url('scoring/index.php'));
}
$statement = $pdo->prepare('SELECT * FROM scoring_results WHERE application_id = ? ORDER BY id DESC LIMIT 1');
$statement->execute([$applicationId]);
$result = $statement->fetch();
if (!$result) {
    redirect(url('scoring/calculate.php?application_id=' . $applicationId));
}
$expertOverride = isset($_POST['expert_override']) ? 1 : 0;
$allowedRiskLevels = ['low', 'moderate', 'medium', 'high', 'very_high'];
$riskLevel = clean_input($_POST['risk_level'] ?? '');
$overrideReason = nullable_input($_POST['override_reason'] ?? null);
if ($expertOverride) {
    if (!in_array($riskLevel, $allowedRiskLevels, true) || $overrideReason === null) {
        redirect(url('scoring/edit.php?application_id=' . $applicationId));
    }
} else {
    $riskLevel = determine_risk_level($result['final_score']);
    $overrideReason = null;
}
$statement = $pdo->prepare('UPDATE scoring_results SET risk_level = ?, expert_override = ?, override_reason = ? WHERE id = ?');
$statement->execute([$riskLevel, $expertOverride, $overrideReason, $result['id']]);
log_action($pdo, 'update_scoring_override', 'scoring', (int) $result['id'], [
    'risk_level' => $result['risk_level'],
    'expert_override' => (bool) $result['expert_override'],
    'override_reason' => $result['override_reason'],
], [
    'risk_level' => $riskLevel,
    'expert_override' => (bool) $expertOverride,
    'override_reason' => $overrideReason,
]);
redirect(url('scoring/view.php?application_id=' . $applicationId));
