<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Ratio results can be deleted only via POST.');
}

$id = post_int('id');
$returnPeriodId = post_int('financial_period_id');
if (!$id || !($pdo instanceof PDO)) {
    exit('Missing or invalid ratio result ID.');
}

try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('SELECT * FROM financial_ratios WHERE id = ?');
    $statement->execute([$id]);
    $oldRatio = $statement->fetch();
    if (!$oldRatio) {
        throw new RuntimeException('Ratio result was not found.');
    }

    $statement = $pdo->prepare('DELETE FROM financial_ratios WHERE id = ?');
    $statement->execute([$id]);
    log_action($pdo, 'delete_ratio_result', 'financial_ratios', $id, $oldRatio, null);
    $pdo->commit();

    redirect($returnPeriodId ? url('financials/view_period.php?id=' . $returnPeriodId) : url('ratios/index.php'));
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo 'Unable to delete ratio result: ' . e($exception->getMessage());
}
