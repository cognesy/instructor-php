<?php declare(strict_types=1);

use Cognesy\Setup\PublishCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

it('publishes resources from all packages into target root', function () {
    $projectRoot = makeProjectRoot();
    makePackageResource($projectRoot, 'polyglot', 'config/llm/default.yaml', "driver: openai\n");
    makePackageResource($projectRoot, 'http-client', 'config/http/default.yaml', "driver: curl\n");
    mkdir($projectRoot . '/packages/empty', 0777, true);
    file_put_contents($projectRoot . '/packages/empty/composer.json', '{}');

    $exitCode = runPublishCommand($projectRoot, ['target' => 'published']);

    expect($exitCode)->toBe(0);
    expect(file_exists($projectRoot . '/published/polyglot/config/llm/default.yaml'))->toBeTrue();
    expect(file_exists($projectRoot . '/published/http-client/config/http/default.yaml'))->toBeTrue();
    expect(is_dir($projectRoot . '/published/empty'))->toBeFalse();
});

it('skips existing destination without force', function () {
    $projectRoot = makeProjectRoot();
    makePackageResource($projectRoot, 'polyglot', 'config/llm/default.yaml', "driver: openai\n");
    mkdir($projectRoot . '/published/polyglot', 0777, true);
    file_put_contents($projectRoot . '/published/polyglot/existing.txt', 'keep');

    $exitCode = runPublishCommand($projectRoot, ['target' => 'published']);

    expect($exitCode)->toBe(0);
    expect(file_exists($projectRoot . '/published/polyglot/existing.txt'))->toBeTrue();
    expect(file_exists($projectRoot . '/published/polyglot/config/llm/default.yaml'))->toBeFalse();
});

it('overwrites destination when force is enabled', function () {
    $projectRoot = makeProjectRoot();
    makePackageResource($projectRoot, 'polyglot', 'config/llm/default.yaml', "driver: openai\n");
    mkdir($projectRoot . '/published/polyglot', 0777, true);
    file_put_contents($projectRoot . '/published/polyglot/old.txt', 'remove-me');

    $exitCode = runPublishCommand($projectRoot, ['target' => 'published', '--force' => true]);

    expect($exitCode)->toBe(0);
    expect(file_exists($projectRoot . '/published/polyglot/old.txt'))->toBeFalse();
    expect(file_exists($projectRoot . '/published/polyglot/config/llm/default.yaml'))->toBeTrue();
});

it('supports no-op mode without creating files', function () {
    $projectRoot = makeProjectRoot();
    makePackageResource($projectRoot, 'polyglot', 'config/llm/default.yaml', "driver: openai\n");

    $exitCode = runPublishCommand($projectRoot, ['target' => 'published', '--no-op' => true]);

    expect($exitCode)->toBe(0);
    expect(is_dir($projectRoot . '/published'))->toBeFalse();
});

/**
 * @param array<string,mixed> $arguments
 */
function runPublishCommand(string $projectRoot, array $arguments): int
{
    $cwd = getcwd();
    if ($cwd === false) {
        throw new RuntimeException('Unable to read working directory');
    }

    chdir($projectRoot);
    try {
        $application = new Application('test-app', '1.0.0');
        $application->add(new PublishCommand());

        $command = $application->find('publish');
        $tester = new CommandTester($command);
        return $tester->execute(['command' => 'publish', ...$arguments]);
    } finally {
        chdir($cwd);
    }
}

function makeProjectRoot(): string
{
    $root = sys_get_temp_dir() . '/setup-command-' . uniqid('', true);
    mkdir($root, 0777, true);
    file_put_contents($root . '/composer.json', '{}');
    mkdir($root . '/packages', 0777, true);
    return $root;
}

function makePackageResource(string $projectRoot, string $package, string $relativePath, string $content): void
{
    $base = $projectRoot . '/packages/' . $package;
    mkdir($base . '/resources/' . dirname($relativePath), 0777, true);
    file_put_contents($base . '/composer.json', '{}');
    file_put_contents($base . '/resources/' . $relativePath, $content);
}
