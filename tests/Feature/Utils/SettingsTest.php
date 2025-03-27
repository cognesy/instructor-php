<?php

use Cognesy\Utils\Settings;

// Create temporary directory for testing
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/settings_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    // Create test config file
    $testConfigContent = <<<'PHP'
<?php
return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'username' => 'test_user',
        'password' => 'test_password',
    ],
    'app' => [
        'debug' => true,
        'environment' => 'testing',
    ],
];
PHP;

    file_put_contents($this->tempDir . '/test.php', $testConfigContent);

    // Reset settings between tests
    Settings::unset('test');
});

// Clean up temporary directory after tests
afterEach(function () {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }

    // Reset path to default
    $reflectionClass = new ReflectionClass(Settings::class);
    $pathProperty = $reflectionClass->getProperty('path');
    $pathProperty->setAccessible(true);
    $pathProperty->setValue(null, null);

    // Reset settings
    $settingsProperty = $reflectionClass->getProperty('settings');
    $settingsProperty->setAccessible(true);
    $settingsProperty->setValue(null, []);
});

it('can set and get path', function () {
    // Get default path
    $defaultPath = Settings::getPath();

    // Set new path
    Settings::setPath($this->tempDir);

    // Check path was set correctly
    expect(Settings::getPath())->toBe($this->tempDir . '/');
});

it('can load settings from a file', function () {
    Settings::setPath($this->tempDir);

    // Test retrieving values
    expect(Settings::get('test', 'database.host'))->toBe('localhost');
    expect(Settings::get('test', 'database.port'))->toBe(3306);
    expect(Settings::get('test', 'app.debug'))->toBe(true);
});

it('can check if a setting exists', function () {
    Settings::setPath($this->tempDir);

    // Test has method
    expect(Settings::has('test', 'database.host'))->toBeTrue();
    expect(Settings::has('test', 'database.driver'))->toBeFalse();
});

it('can set a setting value', function () {
    Settings::setPath($this->tempDir);

    // Set a new value and test
    Settings::set('test', 'database.port', 5432);
    expect(Settings::get('test', 'database.port'))->toBe(5432);

    // Set a completely new key
    Settings::set('test', 'cache.enabled', true);
    expect(Settings::get('test', 'cache.enabled'))->toBe(true);
});

it('can unset a settings group', function () {
    Settings::setPath($this->tempDir);

    // Load settings first
    Settings::get('test', 'database.host');

    // Unset settings
    Settings::unset('test');

    // Reflection to check if settings were unset
    $reflectionClass = new ReflectionClass(Settings::class);
    $settingsProperty = $reflectionClass->getProperty('settings');
    $settingsProperty->setAccessible(true);
    $settings = $settingsProperty->getValue();

    expect($settings['test'])->toBeEmpty();
});

it('returns default value if setting is not found', function () {
    Settings::setPath($this->tempDir);

    // Get non-existent setting with default
    $default = 'xxxxxx';
    expect(Settings::get('test', 'non.existent', $default))->toBe($default);
});

it('throws exception if setting key is not found without default', function () {
    Settings::setPath($this->tempDir);

    // Try to get non-existent setting without default
    expect(fn() => Settings::get('test', 'non.existent'))
        ->toThrow(Exception::class, "Settings key not found: non.existent in group: test and no default value provided");
});

it('throws exception if group is not provided', function () {
    // Try to get with empty group
    expect(fn() => Settings::get('', 'database.host'))
        ->toThrow(Exception::class, "Settings group not provided");

    // Try to check with empty group
    expect(fn() => Settings::has('', 'database.host'))
        ->toThrow(Exception::class, "Settings group not provided");
});

it('throws exception if settings file is not found', function () {
    Settings::setPath($this->tempDir);

    // Try to load non-existent settings file
    expect(fn() => Settings::get('non_existent', 'key'))
        ->toThrow(Exception::class, "Settings file not found");
});

it('resolves relative paths correctly', function () {
    $relativePath = 'relative/path';

    // Use reflection to access private method
    $reflectionClass = new ReflectionClass(Settings::class);
    $method = $reflectionClass->getMethod('resolvePath');
    $method->setAccessible(true);

    $resolvedPath = $method->invoke(null, $relativePath);

    expect($resolvedPath)->toBeString();
    expect($resolvedPath)->toStartWith('/');
    expect($resolvedPath)->toEndWith('relative/path/');
});

it('maintains absolute paths when resolving', function () {
    // Use an absolute path for testing
    $absolutePath = sys_get_temp_dir() . '/absolute/path';

    // Use reflection to access private method
    $reflectionClass = new ReflectionClass(Settings::class);
    $method = $reflectionClass->getMethod('resolvePath');
    $method->setAccessible(true);

    $resolvedPath = $method->invoke(null, $absolutePath);

    // Expected path is the absolute path + directory separator
    $expectedPath = rtrim($absolutePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    expect($resolvedPath)->toBe($expectedPath);
});

it('correctly identifies absolute paths', function () {
    // Use reflection to access private method
    $reflectionClass = new ReflectionClass(Settings::class);
    $method = $reflectionClass->getMethod('isAbsolutePath');
    $method->setAccessible(true);

    // Unix-style absolute path
    expect($method->invoke(null, '/absolute/path'))->toBeTrue();

    // Windows-style absolute path
    expect($method->invoke(null, 'C:\\absolute\\path'))->toBeTrue();

    // Relative path
    expect($method->invoke(null, 'relative/path'))->toBeFalse();
});

it('reads environment variable for config path if set', function () {
    // Mock the environment variable
    $_ENV['INSTRUCTOR_CONFIG_PATH'] = $this->tempDir;

    // Reset path to force reading from environment
    $reflectionClass = new ReflectionClass(Settings::class);
    $pathProperty = $reflectionClass->getProperty('path');
    $pathProperty->setAccessible(true);
    $pathProperty->setValue(null, null);

    // Get path should now use the environment variable
    expect(Settings::getPath())->toBe($this->tempDir . '/');

    // Clean up
    unset($_ENV['INSTRUCTOR_CONFIG_PATH']);
});