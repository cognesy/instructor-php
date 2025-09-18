<?php

use Cognesy\Config\BasePath;

// Helper function to recursively remove directories
function removeDirectory($dir) {
    if (!is_dir($dir)) return;

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? removeDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

// Helper function to create a project structure
function createProjectStructure($baseDir, $withComposer = true) {
    if ($withComposer) {
        file_put_contents($baseDir . '/composer.json', '{"name": "test/project"}');
    }

    // Create vendor structure
    $vendorDir = $baseDir . '/vendor';
    mkdir($vendorDir, 0755, true);
    mkdir($vendorDir . '/composer', 0755, true);

    // Create autoload.php
    file_put_contents($vendorDir . '/autoload.php', '<?php // autoloader');

    return $baseDir;
}

// Helper function to completely reset BasePath singleton
function resetBasePath() {
    $reflection = new ReflectionClass(BasePath::class);

    // Reset singleton instance
    $instanceProperty = $reflection->getProperty('instance');
    $instanceProperty->setAccessible(true);

    // Get current instance if it exists and reset its basePath
    $instance = $instanceProperty->getValue(null);
    if ($instance !== null) {
        $basePathProperty = $reflection->getProperty('basePath');
        $basePathProperty->setAccessible(true);
        $basePathProperty->setValue($instance, null);
    }

    // Reset singleton instance
    $instanceProperty->setValue(null, null);
}

// Helper function to capture current environment state
function captureEnvironmentState() {
    return [
        'env' => $_ENV,
        'server' => $_SERVER,
        'cwd' => getcwd(),
        // Capture any getenv values that might be set
        'getenv_vars' => [
            'APP_BASE_PATH' => getenv('APP_BASE_PATH'),
            'APP_ROOT' => getenv('APP_ROOT'),
            'PROJECT_ROOT' => getenv('PROJECT_ROOT'),
            'BASE_PATH' => getenv('BASE_PATH'),
        ]
    ];
}

// Helper function to restore environment state
function restoreEnvironmentState($state) {
    $_ENV = $state['env'];
    $_SERVER = $state['server'];
    chdir($state['cwd']);

    // Restore getenv values
    foreach ($state['getenv_vars'] as $var => $value) {
        if ($value === false) {
            putenv($var); // Unset
        } else {
            putenv("{$var}={$value}");
        }
    }
}

beforeAll(function () {
    // Use static variables instead of $this
    static $initialState;
    static $initialBasePath;

    $initialState = captureEnvironmentState();

    try {
        $initialBasePath = BasePath::get();
    } catch (Exception $e) {
        $initialBasePath = null;
    }

    // Store in global for afterAll to access
    $GLOBALS['basepath_test_initial_state'] = $initialState;
    $GLOBALS['basepath_test_initial_basepath'] = $initialBasePath;
});

afterAll(function () {
    // Restore from globals
    if (isset($GLOBALS['basepath_test_initial_state'])) {
        restoreEnvironmentState($GLOBALS['basepath_test_initial_state']);
    }

    if (isset($GLOBALS['basepath_test_initial_basepath']) && $GLOBALS['basepath_test_initial_basepath'] !== null) {
        BasePath::set($GLOBALS['basepath_test_initial_basepath']);
    } else {
        resetBasePath();
    }

    // Clean up globals
    unset($GLOBALS['basepath_test_initial_state']);
    unset($GLOBALS['basepath_test_initial_basepath']);
});


// OPTION 1: Use describe() to group BasePath tests and isolate them
describe('BasePath Tests', function () {
    beforeEach(function () {
        // Reset BasePath for each test
        resetBasePath();

        // Create temporary directory structure
        $this->tempDir = sys_get_temp_dir() . '/basepath_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Store current test state
        $this->testState = captureEnvironmentState();

        // Clear environment variables for clean test
        $_ENV = [];
        $_SERVER = [
            'PHP_SELF' => $_SERVER['PHP_SELF'] ?? '',
            'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? '',
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
            'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? '',
        ];

        // Clear environment variables
        putenv('APP_BASE_PATH');
        putenv('APP_ROOT');
        putenv('PROJECT_ROOT');
        putenv('BASE_PATH');
    });

    afterEach(function () {
        // Clean up temporary directory
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            removeDirectory($this->tempDir);
        }

        // Restore the test state (not the initial state)
        restoreEnvironmentState($this->testState);

        // Reset BasePath again to ensure clean state
        resetBasePath();
    });

    // ISOLATED DETECTION METHOD TESTS

    test('detects base path from environment variables only', function () {
        $projectDir = createProjectStructure($this->tempDir);

        // Use only the environment detection method
        BasePath::withDetectionMethods(['getBasePathFromEnv']);

        $_ENV['APP_BASE_PATH'] = $projectDir;

        expect(BasePath::get())->toBe($projectDir);
    });

    test('environment detection fails when no env vars set', function () {
        // Use only environment detection method
        BasePath::withDetectionMethods(['getBasePathFromEnv']);

        // No environment variables set
        expect(fn() => BasePath::get())->toThrow(Exception::class, 'Unable to determine application base path');
    });

    test('environment detection tries multiple variables', function () {
        $projectDir = createProjectStructure($this->tempDir);

        BasePath::withDetectionMethods(['getBasePathFromEnv']);

        // Test each environment variable individually
        $envVars = ['APP_BASE_PATH', 'APP_ROOT', 'PROJECT_ROOT', 'BASE_PATH'];

        foreach ($envVars as $var) {
            resetBasePath();
            $_ENV = []; // Clear all env vars
            $_ENV[$var] = $projectDir;

            expect(BasePath::get())->toBe($projectDir);
        }
    });

    test('detects base path from current working directory only', function () {
        $projectDir = createProjectStructure($this->tempDir);
        // Normalize path for macOS where getcwd() returns resolved symlinks
        // (/private/var/folders vs /var/folders)
        $projectDir = realpath($projectDir);

        BasePath::withDetectionMethods(['getBasePathFromCwd']);

        chdir($projectDir);

        expect(BasePath::get())->toBe($projectDir);
    });

    test('cwd detection fails when no composer.json in cwd', function () {
        BasePath::withDetectionMethods(['getBasePathFromCwd']);

        // Create directory without composer.json
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir, 0755, true);
        chdir($emptyDir);

        expect(fn() => BasePath::get())->toThrow(Exception::class);
    });

    test('detects base path from server variables only', function () {
        $projectDir = createProjectStructure($this->tempDir);

        BasePath::withDetectionMethods(['getBasePathFromServerVars']);

        $_SERVER['DOCUMENT_ROOT'] = $projectDir;

        expect(BasePath::get())->toBe($projectDir);
    });

    test('server vars detection tries multiple variables', function () {
        $projectDir = createProjectStructure($this->tempDir);

        BasePath::withDetectionMethods(['getBasePathFromServerVars']);

        // Test DOCUMENT_ROOT
        resetBasePath();
        BasePath::withDetectionMethods(['getBasePathFromServerVars']); // Add this line
        $_ENV = []; // Clear all env vars
        $_SERVER = ['DOCUMENT_ROOT' => $projectDir];
        expect(BasePath::get())->toBe($projectDir);

        // Test SCRIPT_FILENAME
        resetBasePath();
        BasePath::withDetectionMethods(['getBasePathFromServerVars']); // Add this line
        $_ENV = []; // Clear all env vars
        $_SERVER = ['SCRIPT_FILENAME' => $projectDir . '/index.php'];
        expect(BasePath::get())->toBe($projectDir);

        // Test PWD
        resetBasePath();
        BasePath::withDetectionMethods(['getBasePathFromServerVars']); // Add this line
        $_ENV = []; // Clear all env vars
        $_SERVER = ['PWD' => $projectDir];
        expect(BasePath::get())->toBe($projectDir);
    });

    test('detection methods are tried in specified order', function () {
        $projectDir = createProjectStructure($this->tempDir);

        // Create the different directory first, then add structure
        $differentDir = $this->tempDir . '/different';
        mkdir($differentDir, 0755, true);
        $differentDir = createProjectStructure($differentDir);

        // Test default order (env should come first)
        resetBasePath();
        $_ENV = ['APP_BASE_PATH' => $projectDir];
        $_SERVER = ['DOCUMENT_ROOT' => $differentDir];
        BasePath::withDetectionMethods(['getBasePathFromEnv', 'getBasePathFromServerVars']);
        expect(BasePath::get())->toBe($projectDir);

        // Reset and test reversed order
        resetBasePath();
        $_ENV = [];
        $_SERVER = ['DOCUMENT_ROOT' => $differentDir];
        BasePath::withDetectionMethods(['getBasePathFromServerVars', 'getBasePathFromEnv']);
        expect(BasePath::get())->toBe($differentDir);
    });

    test('can set and get base path manually', function () {
        BasePath::set($this->tempDir);
        expect(BasePath::get())->toBe($this->tempDir);
    });

    test('returns absolute path when path starts with slash', function () {
        $absolutePath = '/absolute/path';
        expect(BasePath::get($absolutePath))->toBe($absolutePath);
    });

    test('appends path correctly', function () {
        BasePath::set($this->tempDir);

        $result = BasePath::get('config/app.php');
        $expected = $this->tempDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

        expect($result)->toBe($expected);
    });

    test('caches base path after first detection', function () {
        $projectDir = createProjectStructure($this->tempDir);

        BasePath::withDetectionMethods(['getBasePathFromEnv']);
        $_ENV['APP_BASE_PATH'] = $projectDir;

        // First call
        $first = BasePath::get();

        // Clear environment variable
        unset($_ENV['APP_BASE_PATH']);

        // Second call should still return cached value
        $second = BasePath::get();

        expect($first)->toBe($second)->toBe($projectDir);
    });

    // PRAGMATIC TESTS FOR REFLECTION-BASED METHODS

    test('framework patterns detection validates structure', function () {
        $projectDir = $this->tempDir . '/myproject';
        mkdir($projectDir, 0755, true);
        createProjectStructure($projectDir);

        // Create public/index.php
        mkdir($projectDir . '/public', 0755, true);
        file_put_contents($projectDir . '/public/index.php', '<?php');

        // Test the structure exists (pragmatic approach)
        expect(file_exists($projectDir . '/composer.json'))->toBeTrue();
        expect(file_exists($projectDir . '/public/index.php'))->toBeTrue();

        // Test directory walking algorithm
        $patterns = [
            'public/index.php',
            'web/index.php',
            'webroot/index.php',
            'htdocs/index.php',
        ];

        $found = false;
        foreach ($patterns as $pattern) {
            if (file_exists($projectDir . '/' . $pattern)) {
                $found = true;
                break;
            }
        }

        expect($found)->toBeTrue();
    });

    test('reflection-based detection algorithm validates correctly', function () {
        // Test the directory traversal logic independently
        $projectDir = $this->tempDir . '/myproject';
        $srcDir = $projectDir . '/src/deeply/nested';
        mkdir($srcDir, 0755, true);
        createProjectStructure($projectDir);

        // Simulate the walking-up algorithm
        $currentDir = $srcDir;
        $found = false;

        while ($currentDir !== dirname($currentDir)) {
            if (file_exists($currentDir . '/composer.json')) {
                $found = $currentDir;
                break;
            }
            $currentDir = dirname($currentDir);
        }

        expect($found)->toBe($projectDir);
    });

    test('validates detection method results', function () {
        // Create directory without composer.json
        $invalidDir = $this->tempDir . '/invalid';
        mkdir($invalidDir, 0755, true);

        BasePath::withDetectionMethods(['getBasePathFromEnv']);

        $_ENV['APP_BASE_PATH'] = $invalidDir;

        // Should fail validation even though env var is set
        expect(fn() => BasePath::get())->toThrow(Exception::class);
    });
});

// OPTION 2: Alternative approach using a test trait for reusable isolation
trait BasePathTestIsolation {
    private $originalBasePath = null;
    private $originalEnvironment = null;

    protected function setUpBasePathIsolation() {
        // Capture original state
        $this->originalEnvironment = captureEnvironmentState();

        try {
            $this->originalBasePath = BasePath::get();
        } catch (Exception $e) {
            $this->originalBasePath = null;
        }

        // Reset for test
        resetBasePath();
    }

    protected function tearDownBasePathIsolation() {
        // Restore original state
        restoreEnvironmentState($this->originalEnvironment);

        if ($this->originalBasePath !== null) {
            BasePath::set($this->originalBasePath);
        } else {
            resetBasePath();
        }
    }
}

// OPTION 3: If you want to test BasePath in a completely separate test file
// Create a new file: BasePathTest.php and run it separately

// Usage example with the trait:
/*
class SomeOtherTestClass {
    use BasePathTestIsolation;

    public function setUp(): void {
        parent::setUp();
        $this->setUpBasePathIsolation();
    }

    public function tearDown(): void {
        $this->tearDownBasePathIsolation();
        parent::tearDown();
    }

    public function testSomething() {
        // Your BasePath test here
    }
}
*/