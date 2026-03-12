<?php declare(strict_types=1);

use Cognesy\Setup\ConfigCacheCommand;
use Cognesy\Setup\ConfigPublishCommand;
use Cognesy\Setup\ConfigValidateCommand;
use Cognesy\Setup\PublishCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

it('compiles a cache artifact from a validated published config tree', function () {
    $projectRoot = configCacheProjectRoot();
    configCachePackage(
        $projectRoot,
        'polyglot',
        'polyglot',
        [
            'resources/config/llm/default.yaml' => "driver: openai\nmodel: gpt-4o-mini\n",
            'resources/config/embed/default.yaml' => "driver: openai\nmodel: text-embedding-3-small\n",
        ],
    );

    expect(runConfigCachePublish($projectRoot, ['target' => 'published']))->toBe(0)
        ->and(runConfigCacheCommand($projectRoot, [
            'target' => 'published',
            '--cache-path' => 'var/cache/test-config.php',
            '--schema-version' => '7',
        ]))->toBe(0);

    /** @var array<string, mixed> $payload */
    $payload = require $projectRoot . '/var/cache/test-config.php';

    expect($payload['_meta']['schema_version'])->toBe(7)
        ->and($payload['_meta'])->toHaveKeys(['files_hash', 'env_hash', 'generated_at', 'file_count', 'files'])
        ->and($payload['config'])->toHaveKeys(['polyglot']);
});

it('fails instead of writing a cache artifact when validation fails', function () {
    $projectRoot = configCacheProjectRoot();
    configCachePackage(
        $projectRoot,
        'templates',
        'templates',
        [
            'resources/config/prompt/presets/system.yaml' => "engine: twig\n",
        ],
    );

    expect(runConfigCachePublish($projectRoot, ['target' => 'published']))->toBe(0)
        ->and(runConfigCacheCommand($projectRoot, [
            'target' => 'published',
            '--cache-path' => 'var/cache/bad-config.php',
        ]))->toBe(1)
        ->and(file_exists($projectRoot . '/var/cache/bad-config.php'))->toBeFalse();
});

/**
 * @param array<string, mixed> $arguments
 */
function runConfigCachePublish(string $projectRoot, array $arguments): int
{
    $cwd = getcwd();
    if ($cwd === false) {
        throw new RuntimeException('Unable to read working directory');
    }

    chdir($projectRoot);
    try {
        $application = new Application('test-app', '1.0.0');
        $application->add(new ConfigPublishCommand());
        $application->add(new ConfigValidateCommand());
        $application->add(new ConfigCacheCommand());
        $application->add(new PublishCommand());

        $command = $application->find('config:publish');
        $tester = new CommandTester($command);

        return $tester->execute(['command' => 'config:publish', ...$arguments]);
    } finally {
        chdir($cwd);
    }
}

/**
 * @param array<string, mixed> $arguments
 */
function runConfigCacheCommand(string $projectRoot, array $arguments): int
{
    $cwd = getcwd();
    if ($cwd === false) {
        throw new RuntimeException('Unable to read working directory');
    }

    chdir($projectRoot);
    try {
        $application = new Application('test-app', '1.0.0');
        $application->add(new ConfigPublishCommand());
        $application->add(new ConfigValidateCommand());
        $application->add(new ConfigCacheCommand());
        $application->add(new PublishCommand());

        $command = $application->find('config:cache');
        $tester = new CommandTester($command);

        return $tester->execute(['command' => 'config:cache', ...$arguments]);
    } finally {
        chdir($cwd);
    }
}

function configCacheProjectRoot(): string
{
    $root = sys_get_temp_dir() . '/setup-config-cache-' . uniqid('', true);
    mkdir($root, 0777, true);
    file_put_contents($root . '/composer.json', '{}');
    mkdir($root . '/packages', 0777, true);

    return $root;
}

/**
 * @param array<string, string> $files
 */
function configCachePackage(string $projectRoot, string $package, string $namespace, array $files): void
{
    $base = $projectRoot . '/packages/' . $package;
    mkdir($base, 0777, true);
    file_put_contents(
        $base . '/composer.json',
        json_encode([
            'extra' => [
                'instructor' => [
                    'config' => [
                        'namespace' => $namespace,
                        'paths' => array_keys($files),
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    );

    foreach ($files as $relative => $content) {
        $path = $base . '/' . $relative;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, $content);
    }
}
