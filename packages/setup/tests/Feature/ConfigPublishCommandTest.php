<?php declare(strict_types=1);

use Cognesy\Setup\ConfigPublishCommand;
use Cognesy\Setup\PublishCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

it('publishes config files into namespace-scoped target paths', function () {
    $projectRoot = configPublishProjectRoot();
    configPublishPackageWithMetadata(
        $projectRoot,
        'polyglot',
        'polyglot',
        ['resources/config/llm/default.yaml' => "driver: openai\n"],
    );

    $exitCode = runConfigPublishCommand($projectRoot, ['target' => 'published']);

    expect($exitCode)->toBe(0)
        ->and(file_exists($projectRoot . '/published/polyglot/llm/default.yaml'))->toBeTrue()
        ->and(file_exists($projectRoot . '/published/polyglot/config/llm/default.yaml'))->toBeFalse();
});

it('supports dry-run mode without creating files', function () {
    $projectRoot = configPublishProjectRoot();
    configPublishPackageWithMetadata(
        $projectRoot,
        'http-client',
        'http-client',
        ['resources/config/http/default.yaml' => "driver: curl\n"],
    );

    $exitCode = runConfigPublishCommand($projectRoot, ['target' => 'published', '--no-op' => true]);

    expect($exitCode)->toBe(0)
        ->and(is_dir($projectRoot . '/published'))->toBeFalse();
});

it('overwrites existing files when force is enabled', function () {
    $projectRoot = configPublishProjectRoot();
    configPublishPackageWithMetadata(
        $projectRoot,
        'polyglot',
        'polyglot',
        ['resources/config/llm/default.yaml' => "driver: openai\n"],
    );
    mkdir($projectRoot . '/published/polyglot/llm', 0777, true);
    file_put_contents($projectRoot . '/published/polyglot/llm/default.yaml', "driver: old\n");

    $exitCode = runConfigPublishCommand($projectRoot, ['target' => 'published', '--force' => true]);

    expect($exitCode)->toBe(0)
        ->and(file_get_contents($projectRoot . '/published/polyglot/llm/default.yaml'))->toContain('openai');
});

it('keeps the legacy publish command available', function () {
    $projectRoot = configPublishProjectRoot();
    configPublishLegacyResource($projectRoot, 'polyglot', 'config/llm/default.yaml', "driver: openai\n");

    $exitCode = runLegacyPublishCommand($projectRoot, ['target' => 'published']);

    expect($exitCode)->toBe(0)
        ->and(file_exists($projectRoot . '/published/polyglot/config/llm/default.yaml'))->toBeTrue();
});

/**
 * @param array<string, mixed> $arguments
 */
function runConfigPublishCommand(string $projectRoot, array $arguments): int
{
    $cwd = getcwd();
    if ($cwd === false) {
        throw new RuntimeException('Unable to read working directory');
    }

    chdir($projectRoot);
    try {
        $application = new Application('test-app', '1.0.0');
        $application->addCommand(new ConfigPublishCommand());
        $application->addCommand(new PublishCommand());

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
function runLegacyPublishCommand(string $projectRoot, array $arguments): int
{
    $cwd = getcwd();
    if ($cwd === false) {
        throw new RuntimeException('Unable to read working directory');
    }

    chdir($projectRoot);
    try {
        $application = new Application('test-app', '1.0.0');
        $application->addCommand(new ConfigPublishCommand());
        $application->addCommand(new PublishCommand());

        $command = $application->find('publish');
        $tester = new CommandTester($command);

        return $tester->execute(['command' => 'publish', ...$arguments]);
    } finally {
        chdir($cwd);
    }
}

function configPublishProjectRoot(): string
{
    $root = sys_get_temp_dir() . '/setup-config-publish-' . uniqid('', true);
    mkdir($root, 0777, true);
    file_put_contents($root . '/composer.json', '{}');
    mkdir($root . '/packages', 0777, true);

    return $root;
}

/**
 * @param array<string, string> $files
 */
function configPublishPackageWithMetadata(string $projectRoot, string $package, string $namespace, array $files): void
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

function configPublishLegacyResource(string $projectRoot, string $package, string $relativePath, string $content): void
{
    $base = $projectRoot . '/packages/' . $package;
    mkdir($base . '/resources/' . dirname($relativePath), 0777, true);
    file_put_contents($base . '/composer.json', '{}');
    file_put_contents($base . '/resources/' . $relativePath, $content);
}
