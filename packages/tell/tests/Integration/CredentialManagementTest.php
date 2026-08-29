<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Command\AuthCommand;
use Cognesy\Tell\Command\DescribeCommand;
use Cognesy\Tell\Console\TellApplication;
use Cognesy\Tell\Console\TellOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\Assert;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

it('registers auth on the real Tell application surface', function (): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $output = new BufferedOutput();

    $status = $application->runArgv(['tell', 'auth', 'status', 'openai', '--json'], $output);
    $payload = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and($payload)->toMatchArray([
            'provider' => 'openai',
            'variable' => 'OPENAI_API_KEY',
            'configured' => true,
        ])
        ->and($payload['source'])->toBeIn(['process-environment', 'workspace-env', 'tell-credentials']);
});

it('stores private credentials from stdin without rendering their values', function (): void {
    $factory = tellTestFactory(credentials: []);
    $secret = 'sk-test-$dollar-and-"quote"';
    $tester = new CommandTester(new AuthCommand($factory, static fn (): string => $secret . "\n"));

    $status = $tester->execute([
        'action' => 'set',
        'provider' => 'openai',
        '--stdin' => true,
        '--json' => true,
    ]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $credentials = $factory->paths()->credentials;

    expect($status)->toBe(0)
        ->and($payload)->toMatchArray([
            'provider' => 'openai',
            'variable' => 'OPENAI_API_KEY',
            'configured' => true,
            'source' => 'tell-credentials',
        ])
        ->and($tester->getDisplay())->not->toContain($secret)
        ->and(file_get_contents($credentials))->not->toContain($secret)
        ->and($factory->credentials()->source()->resolve('OPENAI_API_KEY')?->value())->toBe($secret);
    if (PHP_OS_FAMILY !== 'Windows') {
        expect(fileperms($credentials) & 0777)->toBe(0600);
    }
});

it('keeps resolved credential values out of agent descriptions', function (): void {
    $secret = 'description-secret-that-must-not-render';
    $factory = tellTestFactory(credentials: ['OPENAI_API_KEY' => $secret]);
    $tester = new CommandTester(new DescribeCommand($factory));

    $status = $tester->execute(['--json' => true, '--dir' => tellLastTemporaryRoot()]);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->not->toContain($secret);
});

it('reports safe credential provenance in deterministic precedence order', function (): void {
    $factory = tellTestFactory(credentials: []);
    $workspace = tellLastTemporaryRoot();
    $variable = 'TELL_LAYERED_TEST_API_KEY';
    $original = getenv($variable);
    $factory->credentials()->set($variable, 'tell-value');
    file_put_contents($workspace . '/.env', $variable . '="workspace-value"' . "\n");
    putenv($variable . '=process-value');

    try {
        $tester = new CommandTester(new AuthCommand($factory));
        $tester->execute([
            'action' => 'status',
            'provider' => 'layered-test',
            '--variable' => $variable,
            '--dir' => $workspace,
            '--json' => true,
        ]);
        $process = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

        putenv($variable);
        $tester->execute([
            'action' => 'status',
            'provider' => 'layered-test',
            '--variable' => $variable,
            '--dir' => $workspace,
            '--json' => true,
        ]);
        $workspaceSource = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

        unlink($workspace . '/.env');
        $tester->execute([
            'action' => 'status',
            'provider' => 'layered-test',
            '--variable' => $variable,
            '--dir' => $workspace,
            '--json' => true,
        ]);
        $tell = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

        expect($process['source'])->toBe('process-environment')
            ->and($workspaceSource['source'])->toBe('workspace-env')
            ->and($tell['source'])->toBe('tell-credentials');
        expect(json_encode([$process, $workspaceSource, $tell], JSON_THROW_ON_ERROR))
            ->not->toContain('process-value')
            ->not->toContain('workspace-value')
            ->not->toContain('tell-value');
    } finally {
        match ($original) {
            false => putenv($variable),
            default => putenv($variable . '=' . $original),
        };
    }
});

it('requires explicit stdin and removes only Tell-owned credentials', function (): void {
    $factory = tellTestFactory(credentials: ['OPENAI_API_KEY' => 'stored']);
    $invalid = new CommandTester(new AuthCommand($factory));
    $invalidStatus = $invalid->execute(['action' => 'set', 'provider' => 'openai']);

    $remove = new CommandTester(new AuthCommand($factory));
    $firstStatus = $remove->execute(['action' => 'remove', 'provider' => 'openai']);
    $first = Toon::decode($remove->getDisplay());
    $secondStatus = $remove->execute(['action' => 'remove', 'provider' => 'openai']);
    $second = Toon::decode($remove->getDisplay());

    expect($invalidStatus)->toBe(2)
        ->and(Toon::decode($invalid->getDisplay())['error'])->toContain('requires --stdin')
        ->and($firstStatus)->toBe(0)
        ->and($first['removed'])->toBeTrue()
        ->and($secondStatus)->toBe(0)
        ->and($second['removed'])->toBeFalse();
});

it('resolves project connections before user connections with the Tell secret chain', function (): void {
    $factory = tellTestFactory(credentials: ['CUSTOM_API_KEY' => 'custom-key']);
    $workspace = tellLastTemporaryRoot();
    $paths = $factory->paths();
    mkdir($paths->connections, 0700, true);
    file_put_contents($paths->connections . '/custom.yaml', <<<'YAML'
driver: openai
apiUrl: https://user.example/v1
apiKey: "${CUSTOM_API_KEY}"
model: user-model
YAML);
    mkdir($workspace . '/config/llm/presets', 0755, true);
    file_put_contents($workspace . '/config/llm/presets/custom.yaml', <<<'YAML'
driver: openai
apiUrl: https://project.example/v1
apiKey: "${CUSTOM_API_KEY}"
model: project-model
YAML);

    $config = $factory->definition(new TellOptions(
        prompt: 'test',
        connection: 'custom',
        directory: $workspace,
    ))->llmConfig;

    expect($config?->apiUrl)->toBe('https://project.example/v1')
        ->and($config?->model)->toBe('project-model')
        ->and($config?->apiKey)->toBe('custom-key');
});

it('fails before inference when a remote connection has no credential', function (): void {
    $variable = 'MISSING_TEST_API_KEY';
    $original = getenv($variable);
    putenv($variable);
    try {
        $factory = tellTestFactory(credentials: []);
        $workspace = tellLastTemporaryRoot();
        mkdir($factory->paths()->connections, 0700, true);
        file_put_contents($factory->paths()->connections . '/missing-test.yaml', <<<'YAML'
driver: openai
apiUrl: https://missing.example/v1
apiKey: "${MISSING_TEST_API_KEY}"
model: missing-model
YAML);

        expect(fn () => $factory->assertReady(new TellOptions(
            prompt: 'test',
            connection: 'missing-test',
            directory: $workspace,
        )))->toThrow(RuntimeException::class, 'Missing credential MISSING_TEST_API_KEY');
    } finally {
        match ($original) {
            false => putenv($variable),
            default => putenv($variable . '=' . $original),
        };
    }
});

it('rejects credential files readable by other users', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        Assert::markTestSkipped('POSIX permissions are not available on Windows.');
    }
    $factory = tellTestFactory(credentials: []);
    file_put_contents($factory->paths()->credentials, 'OPENAI_API_KEY="unsafe"' . "\n");
    chmod($factory->paths()->credentials, 0644);

    expect(fn () => $factory->credentials()->variables())
        ->toThrow(RuntimeException::class, 'permissions are too broad');
});

it('does not retain malformed credential contents in exceptions', function (): void {
    $factory = tellTestFactory(credentials: []);
    $secret = 'malformed-secret-must-not-escape';
    file_put_contents($factory->paths()->credentials, 'INVALID LINE ' . $secret . "\n");
    chmod($factory->paths()->credentials, 0600);

    try {
        $factory->credentials()->variables();
        Assert::fail('Expected malformed credentials to fail.');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Unable to parse Tell credentials file.')
            ->and($error->getPrevious())->toBeNull()
            ->and((string) $error)->not->toContain($secret);
    }
});
