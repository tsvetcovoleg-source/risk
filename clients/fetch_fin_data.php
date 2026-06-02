<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'clients';
$pageTitle = 'Fetch financial data';
$statuses = ['active', 'inactive', 'watchlist', 'rejected'];
$errors = [];
$client = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('clients/create.php'));
}

set_time_limit(0);

$client = [
    'client_name' => clean_input(post_value('client_name')),
    'idno' => nullable_input(post_value('idno')),
    'legal_form' => nullable_input(post_value('legal_form')),
    'registration_date' => nullable_input(post_value('registration_date')),
    'activity_sector' => nullable_input(post_value('activity_sector')),
    'caem_code' => nullable_input(post_value('caem_code')),
    'address' => nullable_input(post_value('address')),
    'phone' => nullable_input(post_value('phone')),
    'email' => nullable_input(post_value('email')),
    'website' => nullable_input(post_value('website')),
    'status' => clean_input(post_value('status', 'active')),
    'notes' => nullable_input(post_value('notes')),
];

if ($client['idno'] === null || !preg_match('/^\d{5,20}$/', $client['idno'])) {
    $errors[] = 'IDNO is required and must contain 5-20 digits.';
}
if ($client['client_name'] === '') {
    $client['client_name'] = 'IDNO ' . ($client['idno'] ?? 'unknown');
}
if (!in_array($client['status'], $statuses, true)) {
    $errors[] = 'Invalid client status.';
}
if (!is_valid_date($client['registration_date'])) {
    $errors[] = 'Registration date must use YYYY-MM-DD format.';
}
if ($client['email'] !== null && filter_var($client['email'], FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'Email must be valid.';
}
if ($client['website'] !== null && filter_var($client['website'], FILTER_VALIDATE_URL) === false) {
    $errors[] = 'Website must be a valid URL.';
}
if (!$pdo instanceof PDO) {
    $errors[] = $dbConnectionError ?? 'Database connection is currently unavailable.';
}

if (!empty($errors)) {
    $showFetchFinancialButton = true;
    require_once dirname(__DIR__) . '/header.php';
    require_once dirname(__DIR__) . '/sidebar.php';
    ?>
    <section class="page-heading mb-4"><p class="eyebrow mb-2">Client registry</p><h1 class="h2 mb-0">Create new client</h1></section>
    <div class="card border-0 shadow-sm"><div class="card-body p-4"><form method="post" action="<?= e(url('clients/store.php')) ?>" class="row g-3"><?php require __DIR__ . '/_form.php'; ?><div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Save client</button><a class="btn btn-outline-secondary" href="<?= e(url('clients/index.php')) ?>">Back</a></div></form></div></div>
    <?php require_once dirname(__DIR__) . '/footer.php'; exit;
}

function ensure_fin_data_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS fin_data (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT UNSIGNED NOT NULL,
            IDNO VARCHAR(50) NULL,
            REPORT_KEY VARCHAR(50) NULL,
            META_CSV LONGTEXT NULL,
            BIL_CSV LONGTEXT NULL,
            PNL_CSV LONGTEXT NULL,
            EQT_CSV LONGTEXT NULL,
            CF_CSV LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_fin_data_client_id (client_id),
            KEY idx_fin_data_idno (IDNO),
            KEY idx_fin_data_report_key (REPORT_KEY),
            UNIQUE KEY uq_fin_data_client_report (client_id, REPORT_KEY),
            CONSTRAINT fk_fin_data_client
                FOREIGN KEY (client_id) REFERENCES clients (id)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}


function extract_meta_csv_field(?string $csvText, string $fieldName): ?string
{
    if ($csvText === null || $csvText === '') {
        return null;
    }

    foreach (preg_split('/\R/', $csvText) ?: [] as $line) {
        if ($line === '') {
            continue;
        }

        $columns = str_getcsv($line, ';');
        if (($columns[0] ?? null) === $fieldName) {
            $value = trim((string) ($columns[1] ?? ''));
            return $value === '' || $value === '0' ? null : $value;
        }
    }

    return null;
}

function run_financial_fetcher(string $idno): array
{
    $script = dirname(__DIR__) . '/scripts/fetch_financials_by_idno.py';
    $python = trim((string) shell_exec('command -v python3 2>/dev/null')) ?: 'python3';
    $command = escapeshellcmd($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($idno) . ' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    return [
        'exit_code' => $exitCode,
        'output' => implode("\n", $output),
    ];
}

$statement = $pdo->prepare('INSERT INTO clients (client_name, idno, legal_form, registration_date, activity_sector, caem_code, address, phone, email, website, status, notes) VALUES (:client_name, :idno, :legal_form, :registration_date, :activity_sector, :caem_code, :address, :phone, :email, :website, :status, :notes)');
$statement->execute($client);
$clientId = (int) $pdo->lastInsertId();
log_action($pdo, 'create client', 'client', $clientId, null, $client);

ensure_fin_data_table($pdo);
$fetchResult = run_financial_fetcher((string) $client['idno']);

if ($fetchResult['exit_code'] !== 0) {
    log_action($pdo, 'fetch_fin_data_failed', 'client', $clientId, null, [
        'idno' => $client['idno'],
        'exit_code' => $fetchResult['exit_code'],
        'output' => substr($fetchResult['output'], 0, 2000),
    ]);
    redirect(url('clients/view.php?id=' . $clientId . '&fin_error=' . rawurlencode('Financial fetch failed. Check Python dependencies and Depozitar availability.')));
}

$jsonStart = strpos($fetchResult['output'], '{');
$payload = $jsonStart === false ? null : json_decode(substr($fetchResult['output'], $jsonStart), true);

if (!is_array($payload) || !isset($payload['rows']) || !is_array($payload['rows'])) {
    log_action($pdo, 'fetch_fin_data_failed', 'client', $clientId, null, [
        'idno' => $client['idno'],
        'output' => substr($fetchResult['output'], 0, 2000),
    ]);
    redirect(url('clients/view.php?id=' . $clientId . '&fin_error=' . rawurlencode('Financial fetch returned invalid JSON.')));
}

$rows = $payload['rows'];
if ($rows === []) {
    log_action($pdo, 'fetch_fin_data_empty', 'client', $clientId, null, ['idno' => $client['idno']]);
    redirect(url('clients/view.php?id=' . $clientId . '&fin_status=empty'));
}

$firstRow = is_array($rows[0] ?? null) ? $rows[0] : [];
if (str_starts_with($client['client_name'], 'IDNO ') && is_array($firstRow)) {
    $fetchedClientName = extract_meta_csv_field($firstRow['META_CSV'] ?? null, 'Denumirea entităţii juridice');
    if ($fetchedClientName !== null) {
        $statement = $pdo->prepare('UPDATE clients SET client_name = :client_name WHERE id = :id');
        $statement->execute([
            'client_name' => $fetchedClientName,
            'id' => $clientId,
        ]);
        $client['client_name'] = $fetchedClientName;
    }
}

$insert = $pdo->prepare(
    'INSERT INTO fin_data (client_id, IDNO, REPORT_KEY, META_CSV, BIL_CSV, PNL_CSV, EQT_CSV, CF_CSV)
     VALUES (:client_id, :IDNO, :REPORT_KEY, :META_CSV, :BIL_CSV, :PNL_CSV, :EQT_CSV, :CF_CSV)
     ON DUPLICATE KEY UPDATE
        IDNO = VALUES(IDNO),
        META_CSV = VALUES(META_CSV),
        BIL_CSV = VALUES(BIL_CSV),
        PNL_CSV = VALUES(PNL_CSV),
        EQT_CSV = VALUES(EQT_CSV),
        CF_CSV = VALUES(CF_CSV)'
);

$count = 0;
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }

    $insert->execute([
        'client_id' => $clientId,
        'IDNO' => $row['IDNO'] ?? $client['idno'],
        'REPORT_KEY' => $row['REPORT_KEY'] ?? null,
        'META_CSV' => $row['META_CSV'] ?? null,
        'BIL_CSV' => $row['BIL_CSV'] ?? null,
        'PNL_CSV' => $row['PNL_CSV'] ?? null,
        'EQT_CSV' => $row['EQT_CSV'] ?? null,
        'CF_CSV' => $row['CF_CSV'] ?? null,
    ]);
    $count++;
}

log_action($pdo, 'fetch_fin_data', 'client', $clientId, null, [
    'idno' => $client['idno'],
    'rows' => $count,
]);

redirect(url('clients/view.php?id=' . $clientId . '&fin_status=loaded&fin_count=' . $count));
