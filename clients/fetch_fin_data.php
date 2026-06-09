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

function run_shell_command(string $command): array
{
    if (!function_exists('exec')) {
        return [
            'exit_code' => 1,
            'output' => 'PHP exec() is disabled on this server.',
            'command' => $command,
        ];
    }

    $output = [];
    $exitCode = 0;

    exec($command . ' 2>&1', $output, $exitCode);

    return [
        'exit_code' => $exitCode,
        'output' => implode("\n", $output),
        'command' => $command,
    ];
}

function python_version(string $python): ?array
{
    $result = run_shell_command(
        escapeshellarg($python) . ' -c ' . escapeshellarg('import sys; print("%d.%d.%d" % sys.version_info[:3])')
    );

    if ($result['exit_code'] !== 0) {
        return null;
    }

    $version = trim($result['output']);
    if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches)) {
        return null;
    }

    return [
        'raw' => $version,
        'major' => (int) $matches[1],
        'minor' => (int) $matches[2],
        'patch' => (int) $matches[3],
    ];
}

function is_supported_financial_fetcher_python(?array $version): bool
{
    if ($version === null) {
        return false;
    }

    return $version['major'] > 3 || ($version['major'] === 3 && $version['minor'] >= 8);
}

function compare_python_versions(array $left, array $right): int
{
    foreach (['major', 'minor', 'patch'] as $field) {
        if ($left[$field] === $right[$field]) {
            continue;
        }

        return $left[$field] <=> $right[$field];
    }

    return 0;
}

function python_candidates(): array
{
    $candidates = [];

    if (!function_exists('shell_exec')) {
        return $candidates;
    }

    foreach (['python3.12', 'python3.11', 'python3.10', 'python3.9', 'python3.8', 'python3', 'python'] as $binary) {
        $path = trim((string) shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));
        if ($path !== '') {
            $candidates[] = $path;
        }
    }

    foreach ([
        '/opt/alt/python312/bin/python3',
        '/opt/alt/python311/bin/python3',
        '/opt/alt/python310/bin/python3',
        '/opt/alt/python39/bin/python3',
        '/opt/alt/python38/bin/python3',
        '/usr/local/bin/python3.12',
        '/usr/local/bin/python3.11',
        '/usr/local/bin/python3.10',
        '/usr/local/bin/python3.9',
        '/usr/local/bin/python3.8',
        '/usr/bin/python3.12',
        '/usr/bin/python3.11',
        '/usr/bin/python3.10',
        '/usr/bin/python3.9',
        '/usr/bin/python3.8',
        '/bin/python3',
    ] as $path) {
        if (is_file($path) && is_executable($path)) {
            $candidates[] = $path;
        }
    }

    return array_values(array_unique($candidates));
}

function find_python_executable(?array &$debug = null): ?string
{
    $bestSupported = null;
    $bestSupportedVersion = null;
    $bestAny = null;
    $bestAnyVersion = null;
    $candidateDebug = [];

    foreach (python_candidates() as $candidate) {
        $version = python_version($candidate);
        $candidateDebug[] = [
            'path' => $candidate,
            'version' => $version['raw'] ?? null,
            'supported' => is_supported_financial_fetcher_python($version),
        ];

        if ($version === null) {
            continue;
        }

        if ($bestAnyVersion === null || compare_python_versions($version, $bestAnyVersion) > 0) {
            $bestAny = $candidate;
            $bestAnyVersion = $version;
        }

        if (is_supported_financial_fetcher_python($version)
            && ($bestSupportedVersion === null || compare_python_versions($version, $bestSupportedVersion) > 0)) {
            $bestSupported = $candidate;
            $bestSupportedVersion = $version;
        }
    }

    if (is_array($debug)) {
        $debug[] = [
            'step' => 'python_candidates',
            'candidates' => $candidateDebug,
            'selected_supported_python' => $bestSupported,
            'selected_supported_version' => $bestSupportedVersion['raw'] ?? null,
            'best_available_python' => $bestAny,
            'best_available_version' => $bestAnyVersion['raw'] ?? null,
        ];
    }

    return $bestSupported ?? $bestAny;
}

function default_financial_fetcher_requirements(): array
{
    return [
        'beautifulsoup4==4.12.3',
        'lxml==4.9.4',
        'pandas==1.5.3',
        'playwright==1.48.0',
    ];
}

function ensure_financial_fetcher_runtime(): array
{
    $debug = [];
    $rootDir = dirname(__DIR__);
    $runtimeDir = $rootDir . '/runtime/financial_fetcher';
    $venvDir = $runtimeDir . '/venv';
    $browserDir = $runtimeDir . '/ms-playwright';
    $requirements = $rootDir . '/scripts/requirements-financials.txt';
    $defaultRequirements = default_financial_fetcher_requirements();
    $effectiveRequirements = $requirements;
    $installMarker = $runtimeDir . '/requirements.installed';
    $browserMarker = $runtimeDir . '/chromium.installed';

    $debug[] = [
        'step' => 'runtime_start',
        'php_user' => function_exists('get_current_user') ? get_current_user() : null,
        'disabled_functions' => ini_get('disable_functions') ?: '',
        'root_dir' => $rootDir,
        'runtime_dir' => $runtimeDir,
        'requirements' => $requirements,
        'requirements_exists' => is_file($requirements),
        'default_requirements' => $defaultRequirements,
        'runtime_version' => 'requirements-fallback-v2',
    ];

    if (!is_dir($runtimeDir) && !mkdir($runtimeDir, 0775, true) && !is_dir($runtimeDir)) {
        $debug[] = ['step' => 'mkdir_runtime_failed', 'runtime_dir' => $runtimeDir];
        return [
            'ok' => false,
            'message' => 'Cannot create runtime directory for Python financial fetcher: ' . $runtimeDir,
            'output' => '',
            'debug' => $debug,
        ];
    }

    $debug[] = [
        'step' => 'runtime_ready',
        'runtime_writable' => is_writable($runtimeDir),
        'runtime_permissions' => substr(sprintf('%o', fileperms($runtimeDir) ?: 0), -4),
    ];

    if (!is_file($requirements)) {
        $effectiveRequirements = $runtimeDir . '/requirements-default.txt';
        $requirementsWriteResult = @file_put_contents($effectiveRequirements, implode("\n", $defaultRequirements) . "\n");
        $debug[] = [
            'step' => 'requirements_file_missing_created_runtime_default',
            'source_requirements' => $requirements,
            'effective_requirements' => $effectiveRequirements,
            'write_ok' => $requirementsWriteResult !== false,
            'default_requirements' => $defaultRequirements,
        ];
    }

    $systemPython = find_python_executable($debug);
    $systemPythonVersion = $systemPython !== null ? python_version($systemPython) : null;
    $debug[] = ['step' => 'python_lookup', 'python' => $systemPython, 'version' => $systemPythonVersion['raw'] ?? null];
    if ($systemPython === null) {
        return [
            'ok' => false,
            'message' => 'Python is not available on the server. Install python3 to fetch financial data.',
            'output' => '',
            'debug' => $debug,
        ];
    }

    if (!is_supported_financial_fetcher_python($systemPythonVersion)) {
        return [
            'ok' => false,
            'message' => 'Python 3.8 or newer is required for the financial fetcher. The best available Python is ' . ($systemPythonVersion['raw'] ?? 'unknown') . '. Ask hosting support to enable Python 3.8+ (for example /opt/alt/python311/bin/python3).',
            'output' => 'Unsupported Python version: ' . ($systemPythonVersion['raw'] ?? 'unknown'),
            'debug' => $debug,
        ];
    }

    $python = $systemPython;
    $usingVenv = false;
    $venvPython = $venvDir . '/bin/python';

    if (!is_file($venvPython)) {
        $venvResult = run_shell_command(escapeshellarg($systemPython) . ' -m venv ' . escapeshellarg($venvDir));
        $debug[] = [
            'step' => 'create_venv',
            'exit_code' => $venvResult['exit_code'],
            'command' => $venvResult['command'],
            'output_tail' => substr($venvResult['output'], -1200),
        ];

        if ($venvResult['exit_code'] !== 0) {
            $debug[] = [
                'step' => 'venv_fallback_to_system_python',
                'reason' => 'venv creation failed, usually because ensurepip/python3-venv is unavailable on shared hosting',
                'python' => $systemPython,
            ];
        }
    } else {
        $debug[] = ['step' => 'venv_exists', 'venv_python' => $venvPython];
    }

    if (is_file($venvPython)) {
        $pipCheck = run_shell_command(escapeshellarg($venvPython) . ' -m pip --version');
        $debug[] = [
            'step' => 'venv_pip_check',
            'exit_code' => $pipCheck['exit_code'],
            'command' => $pipCheck['command'],
            'output_tail' => substr($pipCheck['output'], -1200),
        ];

        $venvVersion = python_version($venvPython);
        $debug[] = [
            'step' => 'venv_python_version_check',
            'version' => $venvVersion['raw'] ?? null,
            'supported' => is_supported_financial_fetcher_python($venvVersion),
        ];

        if ($pipCheck['exit_code'] === 0 && is_supported_financial_fetcher_python($venvVersion)) {
            $python = $venvPython;
            $usingVenv = true;
        } else {
            $debug[] = [
                'step' => 'venv_fallback_to_system_python',
                'reason' => 'venv exists but pip is not available inside it or its Python version is unsupported',
                'python' => $systemPython,
            ];
        }
    }

    $dependencyCheck = run_shell_command(
        escapeshellarg($python) . ' -c ' . escapeshellarg('import pandas, bs4; from playwright.async_api import async_playwright')
    );
    $dependenciesAvailable = $dependencyCheck['exit_code'] === 0;
    $debug[] = [
        'step' => 'dependency_import_check',
        'python' => $python,
        'using_venv' => $usingVenv,
        'exit_code' => $dependencyCheck['exit_code'],
        'command' => $dependencyCheck['command'],
        'output_tail' => substr($dependencyCheck['output'], -1600),
    ];

    if (!$dependenciesAvailable && !is_file($effectiveRequirements)) {
        return [
            'ok' => false,
            'message' => 'Cannot prepare Python dependency list for financial fetcher.',
            'output' => 'Requirements file not found and runtime default requirements file could not be created: ' . $effectiveRequirements,
            'debug' => $debug,
        ];
    }

    $requirementsAreFresh = $dependenciesAvailable
        || (is_file($installMarker)
            && filemtime($installMarker) !== false
            && is_file($effectiveRequirements)
            && filemtime($effectiveRequirements) !== false
            && filemtime($installMarker) >= filemtime($effectiveRequirements));
    $debug[] = [
        'step' => 'requirements_check',
        'python' => $python,
        'requirements' => $requirements,
        'effective_requirements' => $effectiveRequirements,
        'requirements_fresh' => $requirementsAreFresh,
        'dependencies_available' => $dependenciesAvailable,
        'install_mode' => $usingVenv ? 'venv' : 'system_user',
        'requirements_source' => is_file($requirements) ? 'file' : 'runtime_built_in_defaults',
    ];

    if (!$requirementsAreFresh) {
        $pipCommand = escapeshellarg($python) . ' -m pip install --disable-pip-version-check --no-input ';
        if (!$usingVenv) {
            $pipCommand .= '--user ';
        }

        if (is_file($effectiveRequirements)) {
            $pipCommand .= '-r ' . escapeshellarg($effectiveRequirements);
        } else {
            $pipCommand .= implode(' ', array_map('escapeshellarg', $defaultRequirements));
        }

        $pipResult = run_shell_command($pipCommand);
        $debug[] = [
            'step' => 'pip_install',
            'exit_code' => $pipResult['exit_code'],
            'command' => $pipResult['command'],
            'output_tail' => substr($pipResult['output'], -2000),
        ];
        if ($pipResult['exit_code'] !== 0) {
            return [
                'ok' => false,
                'message' => $usingVenv
                    ? 'Cannot install Python dependencies for financial fetcher inside virtualenv.'
                    : 'Cannot install Python dependencies for financial fetcher with system Python --user. Ask hosting support to enable pip/user installs or install the packages manually.',
                'output' => $pipResult['output'],
                'debug' => $debug,
            ];
        }

        @touch($installMarker);

        $dependencyCheck = run_shell_command(
            escapeshellarg($python) . ' -c ' . escapeshellarg('import pandas, bs4; from playwright.async_api import async_playwright')
        );
        $debug[] = [
            'step' => 'dependency_import_check_after_install',
            'exit_code' => $dependencyCheck['exit_code'],
            'command' => $dependencyCheck['command'],
            'output_tail' => substr($dependencyCheck['output'], -1600),
        ];
        if ($dependencyCheck['exit_code'] !== 0) {
            return [
                'ok' => false,
                'message' => 'Python dependencies were installed, but imports still fail.',
                'output' => $dependencyCheck['output'],
                'debug' => $debug,
            ];
        }
    }

    if (!is_file($browserMarker)) {
        $playwrightResult = run_shell_command(
            'PLAYWRIGHT_BROWSERS_PATH=' . escapeshellarg($browserDir)
            . ' ' . escapeshellarg($python)
            . ' -m playwright install chromium'
        );
        $debug[] = [
            'step' => 'playwright_install_chromium',
            'exit_code' => $playwrightResult['exit_code'],
            'command' => $playwrightResult['command'],
            'output_tail' => substr($playwrightResult['output'], -2000),
        ];
        if ($playwrightResult['exit_code'] !== 0) {
            return [
                'ok' => false,
                'message' => 'Cannot install Playwright Chromium for financial fetcher.',
                'output' => $playwrightResult['output'],
                'debug' => $debug,
            ];
        }

        @touch($browserMarker);
    } else {
        $debug[] = ['step' => 'chromium_exists', 'browser_dir' => $browserDir];
    }

    $debug[] = [
        'step' => 'runtime_ok',
        'python' => $python,
        'using_venv' => $usingVenv,
        'browser_dir' => $browserDir,
    ];

    return [
        'ok' => true,
        'python' => $python,
        'browser_dir' => $browserDir,
        'output' => '',
        'debug' => $debug,
    ];
}

function format_fetch_error(string $message, string $output = ''): string
{
    $cleanOutput = trim(preg_replace('/\s+/', ' ', $output) ?: '');
    if ($cleanOutput === '') {
        return $message;
    }

    return $message . ' Details: ' . substr($cleanOutput, 0, 700);
}

function format_financial_fetch_debug(array $fetchResult): string
{
    $debug = [
        'exit_code' => $fetchResult['exit_code'] ?? null,
        'error_message' => $fetchResult['error_message'] ?? null,
        'debug' => $fetchResult['debug'] ?? [],
        'output_tail' => isset($fetchResult['output']) ? substr((string) $fetchResult['output'], -2500) : '',
    ];

    $text = json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($text === false) {
        $text = var_export($debug, true);
    }

    return substr($text, 0, 4000);
}

function build_financial_fetch_redirect(int $clientId, string $message, ?string $debug = null): string
{
    $url = url('clients/view.php?id=' . $clientId . '&fin_error=' . rawurlencode($message));
    if ($debug !== null && $debug !== '') {
        $url .= '&fin_debug=' . rawurlencode($debug);
    }

    return $url;
}

function run_financial_fetcher(string $idno): array
{
    $runtime = ensure_financial_fetcher_runtime();
    if (empty($runtime['ok'])) {
        return [
            'exit_code' => 1,
            'output' => $runtime['output'] ?? '',
            'error_message' => format_fetch_error($runtime['message'] ?? 'Financial fetcher runtime is not available.', $runtime['output'] ?? ''),
            'debug' => $runtime['debug'] ?? [],
        ];
    }

    $script = dirname(__DIR__) . '/scripts/fetch_financials_by_idno.py';
    if (!is_file($script)) {
        return [
            'exit_code' => 1,
            'output' => 'Fetcher script not found: ' . $script,
            'error_message' => 'Missing financial fetcher script. Upload scripts/fetch_financials_by_idno.py to the server.',
            'debug' => array_merge($runtime['debug'] ?? [], [[
                'step' => 'fetcher_script_missing',
                'script' => $script,
            ]]),
        ];
    }

    $command = 'PLAYWRIGHT_BROWSERS_PATH=' . escapeshellarg((string) $runtime['browser_dir'])
        . ' ' . escapeshellarg((string) $runtime['python'])
        . ' ' . escapeshellarg($script)
        . ' ' . escapeshellarg($idno);
    $result = run_shell_command($command);

    return [
        'exit_code' => $result['exit_code'],
        'output' => $result['output'],
        'error_message' => format_fetch_error('Financial fetch failed. Depozitar may be unavailable or Chromium system libraries may be missing.', $result['output']),
        'debug' => array_merge($runtime['debug'] ?? [], [[
            'step' => 'run_fetcher_script',
            'exit_code' => $result['exit_code'],
            'command' => $result['command'],
            'output_tail' => substr($result['output'], -2500),
        ]]),
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
    $debugText = format_financial_fetch_debug($fetchResult);
    redirect(build_financial_fetch_redirect($clientId, $fetchResult['error_message'] ?? 'Financial fetch failed. Check Python dependencies and Depozitar availability.', $debugText));
}

$jsonStart = strpos($fetchResult['output'], '{');
$payload = $jsonStart === false ? null : json_decode(substr($fetchResult['output'], $jsonStart), true);

if (!is_array($payload) || !isset($payload['rows']) || !is_array($payload['rows'])) {
    log_action($pdo, 'fetch_fin_data_failed', 'client', $clientId, null, [
        'idno' => $client['idno'],
        'output' => substr($fetchResult['output'], 0, 2000),
    ]);
    $debugText = format_financial_fetch_debug($fetchResult + ['error_message' => 'Financial fetch returned invalid JSON.']);
    redirect(build_financial_fetch_redirect($clientId, 'Financial fetch returned invalid JSON.', $debugText));
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
