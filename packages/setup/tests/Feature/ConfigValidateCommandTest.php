<?php declare(strict_types=1);

use Cognesy\Setup\ConfigPublishCommand;
use Cognesy\Setup\ConfigValidateCommand;
use Cognesy\Setup\PublishCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

it('validates a published config tree successfully', function () {
    $projectRoot = configValidateProjectRoot();
    configValidatePackage(
        $projectRoot,
        'polyglot',
        'polyglot',
        [
            'resources/config/llm/default.yaml' => "driver: openai\nmodel: gpt-4o-mini\n",
            'resources/config/embed/default.yaml' => "driver: openai\nmodel: text-embedding-3-small\n",
        ],
    );

    expect(runConfigValidatePublish($projectRoot, ['target' => 'published']))->toBe(0)
        ->and(runConfigValidateCommand($projectRoot, ['target' => 'published']))->toBe(0);
});

it('fails when an unknown config section is published', function () {
    $projectRoot = configValidateProjectRoot();
    configValidatePackage(
        $projectRoot,
        'polyglot',
        'polyglot',
        [
            'resources/config/llm/default.yaml' => "driver: openai\nmodel: gpt-4o-mini\n",
            'resources/config/embed/default.yaml' => "driver: openai\nmodel: text-embedding-3-small\n",
            'resources/config/llm/custom/rogue.yaml' => "driver: rogue\n",
        ],
    );

    expect(runConfigValidatePublish($projectRoot, ['target' => 'published']))->toBe(0)
        ->and(runConfigValidateCommand($projectRoot, ['target' => 'published']))->toBe(1);
});

it('fails when a required default config is missing', function () {
    $projectRoot = configValidateProjectRoot();
    configValidatePackage(
        $projectRoot,
        'templates',
        'templates',
        [
            'resources/config/prompt/presets/system.yaml' => "engine: twig\n",
        ],
    );

    expect(runConfigValidatePublish($projectRoot, ['target' => 'published']))->toBe(0)
        ->and(runConfigValidateCommand($projectRoot, ['target' => 'published']))->toBe(1);
});

/**
 * @param array<string, mixed> $arguments
 */
function runConfigValidatePublish(string $projectRoot, array $arguments): int
{
    $cwd = getcwd();
    if ($cwd === false) {
        throw new RuntimeException('Unable to read working directory');
    }

    chdir($projectRoot);
    try {
        $application = new Application('test-app', '1.0.0');
        $application->addCommand(new ConfigPublishCommand());
        $application->addCommand(new ConfigValidateCommand());
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
function runConfigValidateCommand(string $projectRoot, array $arguments): int
{
    $cwd = getcwd();
    if ($cwd === false) {
        throw new RuntimeException('Unable to read working directory');
    }

    chdir($projectRoot);
    try {
        $application = new Application('test-app', '1.0.0');
        $application->addCommand(new ConfigPublishCommand());
        $application->addCommand(new ConfigValidateCommand());
        $application->addCommand(new PublishCommand());

        $command = $application->find('config:validate');
        $tester = new CommandTester($command);

        return $tester->execute(['command' => 'config:validate', ...$arguments]);
    } finally {
        chdir($cwd);
    }
}

function configValidateProjectRoot(): string
{
    $root = sys_get_temp_dir() . '/setup-config-validate-' . uniqid('', true);
    mkdir($root, 0777, true);
    file_put_contents($root . '/composer.json', '{}');
    mkdir($root . '/packages', 0777, true);

    return $root;
}

/**
 * @param array<string, string> $files
 */
function configValidatePackage(string $projectRoot, string $package, string $namespace, array $files): void
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
