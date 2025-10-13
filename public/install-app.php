<?php
// ========================
// PHP Backend Functions
// ========================
session_start();

// Define Laravel root directory
$laravelRoot = realpath(__DIR__ . '/../');

// Check if app is already installed
function isAppInstalled()
{
    global $laravelRoot;
    $envPath = $laravelRoot . '/.env';

    if (!file_exists($envPath)) {
        return false;
    }

    $envContent = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envContent as $line) {
        if (strpos(trim($line), 'APP_INSTALLED=') === 0) {
            $value = trim(explode('=', $line, 2)[1] ?? '');
            return strtolower($value) === 'true';
        }
    }

    return false;
}

function isComposerInstalled()
{
    $output = [];
    $returnCode = 0;

    // Check if composer command is available
    exec('composer --version 2>&1', $output, $returnCode);

    return $returnCode === 0;
}

function isNpmInstalled()
{
    $output = [];
    $returnCode = 0;

    // Check if npm command is available
    exec('npm --version 2>&1', $output, $returnCode);

    return $returnCode === 0;
}

// Installation Functions
function testDatabaseConnection($host, $port, $user, $pass)
{
    try {
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->query("SELECT 1");
        return ['success' => true, 'message' => 'Database connection successful'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()];
    }
}

function createDatabase($host, $port, $user, $pass, $dbName)
{
    try {
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        return ['success' => true, 'message' => 'Database created successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database creation failed: ' . $e->getMessage()];
    }
}

function writeEnvFile($db_host, $db_port, $db_name, $db_user, $db_pass)
{
    global $laravelRoot;

    $envExample = $laravelRoot . '/.env.example';
    if (!file_exists($envExample)) {
        return ['success' => false, 'error' => '.env.example file missing'];
    }

    $env = file_get_contents($envExample);
    $env = preg_replace('/DB_HOST=.*/', "DB_HOST=$db_host", $env);
    $env = preg_replace('/DB_PORT=.*/', "DB_PORT=$db_port", $env);
    $env = preg_replace('/DB_DATABASE=.*/', "DB_DATABASE=$db_name", $env);
    $env = preg_replace('/DB_USERNAME=.*/', "DB_USERNAME=$db_user", $env);
    $env = preg_replace('/DB_PASSWORD=.*/', "DB_PASSWORD=$db_pass", $env);

    // Add APP_INSTALLED=false initially
    if (strpos($env, 'APP_INSTALLED=') === false) {
        $env .= "\nAPP_INSTALLED=false";
    }

    if (file_put_contents($laravelRoot . '/.env', $env) === false) {
        return ['success' => false, 'error' => 'Failed to write .env file'];
    }

    return ['success' => true, 'message' => 'Environment file configured'];
}

function runComposerInstall()
{
    global $laravelRoot;

    // Give maximum resources
    ini_set('memory_limit', '-1');
    set_time_limit(0);

    // Skip if already installed
    if (is_dir($laravelRoot . '/vendor') && file_exists($laravelRoot . '/vendor/autoload.php')) {
        return ['success' => true, 'message' => 'Dependencies verified'];
    }

    if (!file_exists($laravelRoot . '/composer.json')) {
        return ['success' => false, 'error' => 'composer.json not found'];
    }

    chdir($laravelRoot);

    $output = [];
    $returnCode = 0;
    $startTime = time();

    // Try the full install first
    exec('composer install --no-dev --optimize-autoloader --no-progress --no-interaction --prefer-dist 2>&1', $output, $returnCode);

    // If that fails, try without optimization (faster)
    if ($returnCode !== 0) {
        $output = [];
        exec('composer install --no-dev --no-progress --no-interaction --prefer-dist 2>&1', $output, $returnCode);
    }

    $executionTime = time() - $startTime;

    if ($returnCode === 0) {
        return [
            'success' => true,
            'message' => "Dependencies installed in {$executionTime} seconds"
        ];
    } else {
        $lastLines = array_slice(array_filter($output), -8);
        return [
            'success' => false,
            'error' => "Composer failed after {$executionTime} seconds:\n" . implode("\n", $lastLines)
        ];
    }
}

function runNpmInstall()
{
    global $laravelRoot;

    // Skip if node_modules already exists
    if (is_dir($laravelRoot . '/node_modules')) {
        return ['success' => true, 'message' => 'Node modules already installed'];
    }

    if (!file_exists($laravelRoot . '/package.json')) {
        return ['success' => true, 'message' => 'No package.json found - skipping npm install'];
    }

    chdir($laravelRoot);

    $output = [];
    $returnCode = 0;

    exec('npm install 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        return ['success' => true, 'message' => 'Node modules installed successfully'];
    } else {
        $lastLines = array_slice(array_filter($output), -6);
        return [
            'success' => false,
            'error' => "NPM install failed:\n" . implode("\n", $lastLines)
        ];
    }
}

function runNpmBuild()
{
    global $laravelRoot;

    if (!file_exists($laravelRoot . '/package.json')) {
        return ['success' => true, 'message' => 'No package.json found - skipping npm build'];
    }

    // Check if build script exists in package.json
    $packageJson = json_decode(file_get_contents($laravelRoot . '/package.json'), true);
    if (!isset($packageJson['scripts']['build'])) {
        return ['success' => true, 'message' => 'No build script found - skipping npm build'];
    }

    chdir($laravelRoot);

    $output = [];
    $returnCode = 0;

    exec('npm run build 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        return ['success' => true, 'message' => 'Frontend assets built successfully'];
    } else {
        $lastLines = array_slice(array_filter($output), -6);
        return [
            'success' => false,
            'error' => "NPM build failed:\n" . implode("\n", $lastLines)
        ];
    }
}

function generateAppKey()
{
    global $laravelRoot;
    chdir($laravelRoot);

    exec('php artisan key:generate --force 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        return ['success' => false, 'error' => 'Key generation failed: ' . implode("\n", array_slice($output, -5))];
    }

    return ['success' => true, 'message' => 'Application key generated'];
}

function runMigrations()
{
    global $laravelRoot;
    chdir($laravelRoot);

    exec('php artisan migrate --force 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        return ['success' => false, 'error' => 'Migrations failed: ' . implode("\n", array_slice($output, -5))];
    }

    return ['success' => true, 'message' => 'Database migrations completed'];
}

function markAppAsInstalled()
{
    global $laravelRoot;

    $envFile = $laravelRoot . '/.env';
    if (!file_exists($envFile)) {
        return ['success' => false, 'error' => '.env file not found'];
    }

    $envContent = file_get_contents($envFile);
    if (strpos($envContent, 'APP_INSTALLED=') !== false) {
        $envContent = preg_replace('/APP_INSTALLED=.*/', 'APP_INSTALLED=true', $envContent);
    } else {
        $envContent .= "\nAPP_INSTALLED=true";
    }

    if (file_put_contents($envFile, $envContent) === false) {
        return ['success' => false, 'error' => 'Failed to update .env file'];
    }

    return ['success' => true, 'message' => 'Application marked as installed'];
}

// ========================
// Handle AJAX Requests FIRST
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers FIRST
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    // Increase limits for installation
    set_time_limit(600);
    ini_set('memory_limit', '512M');
    error_reporting(0);

    // Start output buffering to catch any stray output
    ob_start();

    try {
        // Check if app is already installed
        if (isAppInstalled() && (!isset($_POST['step']) || $_POST['step'] != 3)) {
            throw new Exception('Application is already installed');
        }

        $step = $_POST['step'] ?? 1;
        $action = $_POST['action'] ?? '';

        // Check installation status
        if ($action === 'check_installed') {
            echo json_encode([
                'installed' => isAppInstalled(),
                'app_url' => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')
            ]);
            exit;
        }

        // Step 2: Database Setup (with individual actions)
        if ($step == 2 && $action) {
            $required = ['db_host', 'db_port', 'db_name', 'db_user'];
            $missing = [];

            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                throw new Exception("Missing required fields: " . implode(', ', $missing));
            }

            $db_host = $_POST['db_host'];
            $db_port = $_POST['db_port'];
            $db_name = $_POST['db_name'];
            $db_user = $_POST['db_user'];
            $db_pass = $_POST['db_pass'] ?? '';

            $result = null;

            switch ($action) {
                case 'test_db_connection':
                    $result = testDatabaseConnection($db_host, $db_port, $db_user, $db_pass);
                    break;

                case 'create_database':
                    $result = createDatabase($db_host, $db_port, $db_user, $db_pass, $db_name);
                    break;

                case 'write_env':
                    $result = writeEnvFile($db_host, $db_port, $db_name, $db_user, $db_pass);
                    break;

                case 'composer_install':
                    $result = runComposerInstall(); // This is the long-running one
                    break;

                case 'npm_install':
                    $result = runNpmInstall();
                    break;

                case 'npm_build':
                    $result = runNpmBuild();
                    break;

                case 'generate_key':
                    $result = generateAppKey();
                    break;

                case 'run_migrations':
                    $result = runMigrations();
                    break;

                case 'mark_installed':
                    $result = markAppAsInstalled();
                    break;

                default:
                    throw new Exception('Unknown action: ' . $action);
            }

            if ($result === null) {
                throw new Exception('No result returned from action: ' . $action);
            }

            // Clear any accidental output before JSON
            ob_clean();
            echo json_encode($result);
            exit;
        }

        // Default response for other steps
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Step processed']);
        exit;

    } catch (Exception $e) {
        // Clear any output and send error
        ob_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle GET requests for step content
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['step'])) {
    // Check if app is already installed
    if (isAppInstalled() && $_GET['step'] != 3) {
        echo '<div class="text-center text-red-600 p-4">Application is already installed. <a href="/" class="text-blue-600 underline">Go to application</a></div>';
        exit;
    }

    $step = (int) $_GET['step'];

    if ($step == 1) {
        $allOk = true;
        echo '<div class="space-y-4" id="step1-container">';
        echo '<h2 class="text-xl font-bold text-blue-600">Step 1: System Requirements</h2>';
        echo '<p class="text-gray-600 text-sm">Checking required PHP extensions and server configuration...</p>';

        $reqs = [
            'PHP >= 8.1' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'Composer' => isComposerInstalled(),
            'Node.js (npm)' => isNpmInstalled(),
            'OpenSSL' => extension_loaded('openssl'),
            'PDO' => extension_loaded('pdo'),
            'Mbstring' => extension_loaded('mbstring'),
            'Tokenizer' => extension_loaded('tokenizer'),
            'XML' => extension_loaded('xml'),
            'Ctype' => extension_loaded('ctype'),
            'JSON' => extension_loaded('json'),
        ];

        echo '<ul class="divide-y divide-gray-200 border rounded-lg overflow-hidden border-gray-200">';
        foreach ($reqs as $name => $ok) {
            if (!$ok)
                $allOk = false;
            echo '<li class="p-2 flex justify-between items-center ' . ($ok ? 'bg-green-50' : 'bg-red-50') . '">
                    <span class="font-medium text-gray-700 text-xs">' . $name . '</span>
                    <span class="text-xs">' . ($ok ? '✅' : '❌') . '</span>
                  </li>';
        }
        echo '</ul>';
        echo '<p class="text-gray-500 text-xs text-center mt-2">All items should show ✅ before proceeding.</p>';
        echo '</div>';
        echo '<div id="step-one-complete" data-step-ok="' . ($allOk ? '1' : '0') . '">';
        exit;
    } elseif ($step == 2) {
        echo '
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-blue-600">Step 2: Database Setup</h2>
            <p class="text-gray-600 text-sm">Please provide your database details.</p>
            <form class="space-y-3">
                <div class="grid grid-cols-2 gap-3 border-b border-gray-300 pb-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 pb-2">Database Host</label>
                        <input class="w-full border rounded px-3 py-2 border-gray-300" type="text" name="db_host" value="127.0.0.1" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 pb-2">Port</label>
                        <input class="w-full border rounded px-3 py-2 border-gray-300" type="text" name="db_port" value="3306" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 pb-2">Username</label>
                        <input class="w-full border rounded px-3 py-2 border-gray-300" type="text" name="db_user" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 pb-2">Password</label>
                        <input class="w-full border rounded px-3 py-2 border-gray-300" type="password" name="db_pass" placeholder="Optional">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 pb-2">Database Name</label>
                    <input class="w-full border rounded px-3 py-2 border-gray-300" type="text" name="db_name" required>
                </div>
            </form>
        </div>';
        exit;
    } elseif ($step == 3) {
        $appUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        echo '
        <div class="text-center space-y-5">
            <h2 class="text-2xl font-bold text-green-600">🎉 Installation Complete!</h2>
            <p class="text-gray-600 text-sm">Your application has been successfully installed.</p>
            <a href="' . $appUrl . '" class="inline-block px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">Go to Application →</a>
        </div>';
        exit;
    }
}

// ========================
// If we reach here, it's a regular page load - show the full HTML
// ========================

// Check if app is already installed on regular page load
if (isAppInstalled()) {
    // Get the base URL of the application
    $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    // Redirect to the application root
    header('Location: ' . $baseUrl . '/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <title>📦 Application Setup</title>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen py-8">

    <div class="bg-white shadow-lg rounded-2xl p-8 w-full max-w-xl min-h-full" id="installer-container">
        <h1 class="text-2xl font-bold text-center text-blue-600 mb-6 border-b pb-3 border-gray-200">📦 Application Setup</h1>

        <div id="step-content" class="relative">
            <!-- Loading Spinner -->
            <div id="loading" class="text-center py-6">
                <div class="flex justify-center items-center mb-3">
                    <div class="animate-spin rounded-full h-10 w-10 border-t-4 border-blue-500"></div>
                </div>
                <p class="text-gray-600">Processing... Please wait</p>
            </div>
            <div id="form-container"></div>

            <!-- Installation Progress Log -->
            <div id="install-log" class="bg-gray-50 p-4 mt-4 rounded-lg h-64 overflow-y-auto text-sm text-gray-700 hidden">
                <div class="space-y-2" id="log-content"></div>
            </div>
        </div>

        <div class="flex justify-between mt-8">
            <button id="prevBtn" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm hidden" onclick="prevStep()">← Back</button>
            <button id="nextBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg text-sm" onclick="nextStep()">Next →</button>
        </div>

        <div id="errorBox" class="mt-4 text-red-600 text-xs font-semibold text-center hidden"></div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 4;

        // Check if app is already installed
        window.onload = function () {
            checkInstallationStatus();
        };

        function checkInstallationStatus() {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'check_installed');

            fetch('install-app.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    // First check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            throw new Error('Expected JSON, got: ' + text.substring(0, 100));
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.installed) {
                        window.location.href = data.app_url || '/';
                    } else {
                        loadStep(currentStep);
                    }
                })
                .catch(error => {
                    console.error('Error checking installation status:', error);
                    showError('Failed to check installation status: ' + error.message);
                    loadStep(currentStep);
                });
        }

        function loadStep(step) {
            document.getElementById("install-log").classList.add("hidden");
            document.getElementById("loading").classList.remove("hidden");

            fetch(`install-app.php?step=${step}`)
                .then(response => response.text())
                .then(html => {
                    if (step === 3) {
                        document.getElementById("installer-container").innerHTML = html;
                    } else {
                        document.getElementById("form-container").innerHTML = html;
                        updateButtons();
                    }
                    document.getElementById("loading").classList.add("hidden");
                })
                .catch(error => {
                    console.error('Error loading step:', error);
                    showError('Failed to load step: ' + error.message);
                    document.getElementById("loading").classList.add("hidden");
                });
        }

        function nextStep() {
            if (currentStep === 2) {
                processStep2();
                return;
            }

            const form = document.querySelector("#step-content form");
            if (!form) {
                currentStep++;
                loadStep(currentStep);
                return;
            }

            document.getElementById("loading").classList.remove("hidden");

            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('step', currentStep);

            fetch('install-app.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            throw new Error('Expected JSON, got: ' + text.substring(0, 100));
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        currentStep++;
                        loadStep(currentStep);
                    } else if (data.error) {
                        document.getElementById("loading").classList.add("hidden");
                        showError(data.error);
                    }
                })
                .catch(error => {
                    document.getElementById("loading").classList.add("hidden");
                    showError("Request failed: " + error.message);
                    console.error(error);
                });
        }

        function processStep2() {
            const form = document.querySelector("#step-content form");
            if (!form) {
                showError("No form found for step 2");
                return;
            }

            // Get form values
            const formData = {
                db_host: form.querySelector('[name="db_host"]').value,
                db_port: form.querySelector('[name="db_port"]').value,
                db_name: form.querySelector('[name="db_name"]').value,
                db_user: form.querySelector('[name="db_user"]').value,
                db_pass: form.querySelector('[name="db_pass"]').value
            };

            // Validate required fields before starting
            const required = ['db_host', 'db_port', 'db_name', 'db_user'];
            const missing = required.filter(field => !formData[field]);

            if (missing.length > 0) {
                showError('Missing required fields: ' + missing.join(', '));
                return;
            }

            document.getElementById("loading").classList.remove("hidden");
            document.getElementById("install-log").classList.remove("hidden");
            document.getElementById("nextBtn").disabled = true;

            // Process step 2 with progress updates
            processStepWithProgress(formData);
        }

        function processStepWithProgress(data) {
            const steps = [
                { action: 'test_db_connection', message: 'Testing database connection...' },
                { action: 'create_database', message: 'Creating database...' },
                { action: 'write_env', message: 'Writing configuration...' },
                { action: 'composer_install', message: 'Installing dependencies (this may take several minutes)...' },
                { action: 'npm_install', message: 'Installing Node.js dependencies...' },
                { action: 'npm_build', message: 'Building frontend assets...' },
                { action: 'generate_key', message: 'Generating application key...' },
                { action: 'run_migrations', message: 'Running database migrations...' },
                { action: 'mark_installed', message: 'Finalizing installation...' }
            ];

            let currentStepIndex = 0;

            function processNextStep() {
                if (currentStepIndex >= steps.length) {
                    document.getElementById("loading").classList.add("hidden");
                    document.getElementById("nextBtn").disabled = false;
                    currentStep++;
                    loadStep(currentStep);
                    return;
                }

                const step = steps[currentStepIndex];
                addLog(step.message, 'info');

                const params = new URLSearchParams();
                params.append('ajax', '1');
                params.append('step', '2');
                params.append('action', step.action);

                // Add all data fields
                Object.keys(data).forEach(key => {
                    params.append(key, data[key] || '');
                });

                fetch('install-app.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params
                })
                    .then(response => {
                        // Check if we got a response at all
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error(`Invalid JSON response: ${text.substring(0, 100)}`);
                            }
                        });
                    })
                    .then(result => {
                        if (result && result.success) {
                            addLog(result.message || '✓ Completed', 'success');
                            currentStepIndex++;
                            setTimeout(processNextStep, 1000); // 1 second delay between steps
                        } else {
                            const errorMsg = (result && result.error) || 'Unknown error occurred';
                            addLog('✗ ' + errorMsg, 'error');
                            document.getElementById("loading").classList.add("hidden");
                            document.getElementById("nextBtn").disabled = false;
                            showError('Installation failed: ' + errorMsg);
                        }
                    })
                    .catch(error => {
                        addLog('✗ Request failed: ' + error.message, 'error');
                        document.getElementById("loading").classList.add("hidden");
                        document.getElementById("nextBtn").disabled = false;
                        showError('Request failed: ' + error.message);
                    });
            }

            processNextStep();
        }

        function addLog(message, type = 'info') {
            const logContent = document.getElementById('log-content');
            const logEntry = document.createElement('div');
            logEntry.className = `flex items-center space-x-2 ${type === 'error' ? 'text-red-600' :
                type === 'success' ? 'text-green-600' : 'text-gray-700'
                }`;

            const icon = type === 'error' ? '✗' : type === 'success' ? '✓' : '⏳';
            logEntry.innerHTML = `<span class="font-mono">${icon}</span><span>${message}</span>`;

            logContent.appendChild(logEntry);
            logContent.scrollTop = logContent.scrollHeight;
        }

        function prevStep() {
            if (currentStep > 1) currentStep--;
            loadStep(currentStep);
        }

        function updateButtons() {
            document.getElementById("prevBtn").classList.toggle("hidden", currentStep === 1);
            document.getElementById("nextBtn").innerText = currentStep === totalSteps ? "Finish ✅" : "Next →";

            if (currentStep === 1) {
                const stepComplete = document.getElementById("step-one-complete");
                const stepOk = stepComplete?.dataset.stepOk === '1';

                document.getElementById("nextBtn").disabled = !stepOk;
                document.getElementById("nextBtn").classList.toggle('opacity-50', !stepOk);
                document.getElementById("nextBtn").classList.toggle('cursor-not-allowed', !stepOk);
            } else {
                document.getElementById("nextBtn").disabled = false;
                document.getElementById("nextBtn").classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        function showError(msg) {
            const box = document.getElementById("errorBox");
            box.innerText = msg;
            box.classList.remove("hidden");
        }

        function hideError() {
            const box = document.getElementById("errorBox");
            box.innerText = "";
            box.classList.add("hidden");
        }
    </script>
</body>

</html>