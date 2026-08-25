<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\Command\AgentsCommand;
use Cognesy\Tell\Command\DescribeCommand;
use Cognesy\Tell\Command\SessionsCommand;
use Cognesy\Tell\Command\ToolsCommand;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\TellApplication;
use Cognesy\Tell\TellCommand;
use Composer\InstalledVersions;
use HelgeSverre\Toon\Toon;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

it('boots the real application without global option collisions', function (): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $tester = new ApplicationTester($application);

    $status = $tester->run(['command' => 'tell', '--help' => true]);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('Run one non-interactive agent turn')
        ->toContain('display help for the tell command')
        ->toContain('-v|vv|vvv, --verbose')
        ->toContain('-q, --quiet')
        ->toContain('--no-ansi');
    expect(str_contains($tester->getDisplay(), '--no-color'))->toBeFalse();
    expect(str_contains($tester->getDisplay(), '--yes'))->toBeFalse();
});

it('reports its Composer package version', function (): void {
    $application = new TellApplication(tellTestFactory());
    $package = 'cognesy/instructor-tell';
    $version = InstalledVersions::isInstalled($package)
        ? InstalledVersions::getPrettyVersion($package)
        : InstalledVersions::getRootPackage()['pretty_version'];

    expect($application->getVersion())->toBe($version);
    expect($application->getVersion() === '2.6.0-dev')->toBeFalse();
});

it('routes implicit prompts and keeps named subcommands available', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('implicit ok')));
    $application = new TellApplication($factory);
    $application->setAutoExit(false);

    $promptOutput = new BufferedOutput;
    $promptStatus = $application->runArgv(
        ['tell', '--connection', 'openai', 'hello'],
        $promptOutput,
    );
    $agentsOutput = new BufferedOutput;
    $agentsStatus = $application->runArgv(['tell', 'agents', '--json'], $agentsOutput);
    $agents = json_decode($agentsOutput->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($promptStatus)->toBe(0)
        ->and(Toon::decode($promptOutput->fetch())['answer'])->toBe('implicit ok')
        ->and($agentsStatus)->toBe(0)
        ->and(array_column($agents['agents'], 'name'))->toBe(['default']);
});

it('uses bare invocations for content-first discovery', function (array $arguments): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv($arguments, $output);
    $payload = Toon::decode($output->fetch());

    expect($status)->toBe(0)
        ->and($payload['description'])->toContain('Run and inspect Instructor agents')
        ->and($payload['storage']['home'])->toEndWith('/tell-home')
        ->and($payload['storage']['config'])->toEndWith('/tell-home/config/tell.json')
        ->and($payload['storage']['connections'])->toEndWith('/tell-home/config/connections')
        ->and($payload['storage']['sessions'])->toEndWith('/tell-home/runtime/sessions')
        ->and($payload['storage']['executionTraces'])->toEndWith('/tell-home/logs/executions')
        ->and($payload['storage']['sessionTraces'])->toEndWith('/tell-home/logs/sessions')
        ->and($payload['observability']['executionTraces'])->toBeTrue()
        ->and($payload['observability']['includePayloads'])->toBeFalse()
        ->and($payload['agentCount'])->toBe(1)
        ->and($payload['agents'][0]['name'])->toBe('default');
    expect($payload['help'] === [])->toBeFalse();
    expect(array_key_exists('credentials', $payload['storage']))->toBeFalse();
})->with([
    'empty argv' => [['tell']],
    'empty argv with verbosity' => [['tell', '-v']],
    'separator without a prompt' => [['tell', '--']],
]);

it('renders unknown option errors as structured usage data', function (array $arguments): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv($arguments, $output);
    $display = $output->fetch();
    $payload = Toon::decode($display);

    expect($status)->toBe(2)
        ->and($payload['error'])->toContain('The "--bogus" option does not exist')
        ->and($display)->toContain('Valid flags');
    expect($payload['help'] === [])->toBeFalse();
    expect(str_contains($display, 'Fatal error'))->toBeFalse();
    expect(str_contains($display, 'Stack trace'))->toBeFalse();
})->with([
    'implicit prompt' => [['tell', '--bogus', 'hello']],
    'named subcommand' => [['tell', 'agents', '--bogus']],
]);

it('reports valid flags for the addressed command', function (array $arguments, string $flag, string $other): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv($arguments, $output);
    $display = $output->fetch();

    expect($status)->toBe(2)
        ->and($display)->toContain($flag);
    expect(str_contains($display, $other))->toBeFalse();
})->with([
    'subcommand keeps its own flags' => [['tell', 'agents', '--bogus'], '--fields', '--max-steps'],
    'implicit prompt keeps the tell flags' => [['tell', '--bogus', 'hi'], '--max-steps', '--fields'],
]);

it('resolves a subcommand that follows an option value', function (): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', '--dir', (string) getcwd(), 'agents'], $output);

    expect($status)->toBe(0)
        ->and($output->fetch())->toContain('default');
});

it('never mistakes an option value for a command name', function (): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', '--model', 'gpt-4o-mini', 'agents'], $output);

    // 'agents' is the command; --model belongs to tell, not agents. The error
    // must name that mismatch, never 'Command "gpt-4o-mini" is not defined.'
    $payload = Toon::decode($output->fetch());
    expect($status)->toBe(2)
        ->and($payload['error'])->toContain('The "--model" option does not exist');
    expect(str_contains($payload['error'], 'gpt-4o-mini" is not defined'))->toBeFalse();
});

it('treats a subcommand name after the separator as a prompt', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('literal prompt ok')));
    $application = new TellApplication($factory);
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', '--', 'agents'], $output);

    expect($status)->toBe(0)
        ->and(Toon::decode($output->fetch())['answer'])->toBe('literal prompt ok');
});

it('keeps the legacy prompt, connection, model, and dsn options', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('legacy ok')));
    $tester = new CommandTester(new TellCommand($factory));

    $status = $tester->execute([
        'prompt' => 'hello',
        '-c' => 'openai',
        '-m' => 'test-model',
        '-d' => 'driver=openai,model=test-model',
        '--output' => 'text',
    ], ['capture_stderr_separately' => true]);

    expect($status)->toBe(0)
        ->and(trim($tester->getDisplay()))->toBe('legacy ok');
});

it('validates option combinations with usage exit code two', function (array $arguments): void {
    $tester = new CommandTester(new TellCommand(tellTestFactory()));
    $status = $tester->execute(['prompt' => 'hello', ...$arguments], ['capture_stderr_separately' => true]);

    $error = Toon::decode($tester->getDisplay())['error'];
    expect($status)->toBe(2)
        ->and($tester->getErrorOutput())->toBe('');
    expect($error === '')->toBeFalse();
})->with([
    'bad output' => [['--output' => 'xml']],
    'bad budget' => [['--max-steps' => '0']],
]);

it('returns usage exit code two for invalid session commands', function (array $arguments, string $message): void {
    $tester = new CommandTester(new SessionsCommand(tellTestFactory()));
    $status = $tester->execute($arguments, ['capture_stderr_separately' => true]);

    expect($status)->toBe(2)
        ->and(Toon::decode($tester->getDisplay())['error'])->toContain($message)
        ->and($tester->getErrorOutput())->toBe('');
})->with([
    'unknown action' => [['action' => 'unknown'], 'Unknown sessions action'],
    'missing show id' => [['action' => 'show'], 'Session ID is required'],
    'missing remove id' => [['action' => 'rm'], 'Session ID is required'],
]);

it('resolves connection presets and applies only explicit model overrides', function (): void {
    $factory = tellTestFactory();
    $directory = tellLastTemporaryRoot();
    $resolved = $factory->definition(new TellOptions(
        prompt: 'hello',
        connection: 'openai',
        directory: $directory,
    ))->llmConfig;
    $overridden = $factory->definition(new TellOptions(
        prompt: 'hello',
        connection: 'openai',
        model: 'test-model',
        directory: $directory,
    ))->llmConfig;

    expect($resolved)->toBeInstanceOf(LLMConfig::class);
    expect($overridden)->toBeInstanceOf(LLMConfig::class);
    assert($resolved instanceof LLMConfig);
    assert($overridden instanceof LLMConfig);
    expect($resolved->driver)->toBe('openai')
        ->and($resolved->apiUrl)->toBe('https://api.openai.com/v1')
        ->and($resolved->apiKey)->not->toBe('')
        ->and($overridden->toArray())->toBe($resolved->withOverrides(['model' => 'test-model'])->toArray());
});

it('gives dsn configuration precedence over connection and model', function (): void {
    $factory = tellTestFactory();
    $options = new TellOptions(
        prompt: 'hello',
        connection: 'anthropic',
        model: 'ignored-model',
        dsn: 'driver=openai,model=dsn-model',
        directory: tellLastTemporaryRoot(),
    );
    $config = $factory->definition($options)->llmConfig;

    expect($config)->toBeInstanceOf(LLMConfig::class);
    assert($config instanceof LLMConfig);
    expect($config->toArray()['driver'] ?? null)->toBe('openai')
        ->and($config->toArray()['model'] ?? null)->toBe('dsn-model');
});

it('lists valid definitions and reports malformed files', function (): void {
    $factory = tellTestFactory(userAgents: [
        'reviewer.md' => "---\nname: reviewer\ndescription: Reviews code\n---\nReview carefully.\n",
        'broken.md' => 'not frontmatter',
    ]);
    $tester = new CommandTester(new AgentsCommand($factory));
    $tester->execute(['--json' => true]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect(array_column($payload['agents'], 'name'))->toBe(['default', 'reviewer'])
        ->and($payload['errors'])->toHaveCount(1)
        ->and($payload['errors'][0]['path'])->toEndWith('/broken.md');
});

it('derives tools output from the built loop profile', function (): void {
    $factory = tellTestFactory();
    $tester = new CommandTester(new ToolsCommand($factory));
    $tester->execute(['--json' => true, '--dir' => tellLastTemporaryRoot()]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $options = new TellOptions(prompt: 'tools', directory: tellLastTemporaryRoot());

    expect(array_column($payload['tools'], 'name'))
        ->toBe($factory->build($options)->profile()->tools->names());
});

it('derives describe json from the built loop profile', function (): void {
    $factory = tellTestFactory();
    $tester = new CommandTester(new DescribeCommand($factory));
    $tester->execute(['--json' => true, '--dir' => tellLastTemporaryRoot()]);
    $actual = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $options = new TellOptions(prompt: 'describe', directory: tellLastTemporaryRoot());

    $expected = $factory->build($options)->describe()->toArray();
    expect($actual)->toMatchArray($expected);
    expect($actual['help'] === [])->toBeFalse();
});

it('describes the effective runtime system prompt in json and text', function (): void {
    $factory = tellTestFactory();
    $directory = tellLastTemporaryRoot();

    $jsonTester = new CommandTester(new DescribeCommand($factory));
    $jsonTester->execute(['--json' => true, '--prompt' => true, '--dir' => $directory]);
    $payload = json_decode($jsonTester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    $toonTester = new CommandTester(new DescribeCommand($factory));
    $toonTester->execute(['--prompt' => true, '--dir' => $directory]);
    $toon = Toon::decode($toonTester->getDisplay());

    expect($payload['systemPrompt'])->toStartWith('You are a deterministic test agent.')
        ->toContain('<!-- cognesy-agent-profile:start -->')
        ->toContain('Available tools:')
        ->toContain('<!-- cognesy-agent-profile:end -->')
        ->and($toon['systemPrompt'])->toStartWith('You are a deterministic test agent.')
        ->toContain('<!-- cognesy-agent-profile:start -->');
});

it('selects compact list fields without changing the output format', function (): void {
    $tester = new CommandTester(new AgentsCommand(tellTestFactory()));
    $status = $tester->execute(['--fields' => 'name,label']);
    $payload = Toon::decode($tester->getDisplay());

    expect($status)->toBe(0)
        ->and($payload['agents'][0])->toHaveKeys(['name', 'label']);
    expect(array_key_exists('description', $payload['agents'][0]))->toBeFalse();
});

it('makes empty session state explicit and actionable', function (): void {
    $tester = new CommandTester(new SessionsCommand(tellTestFactory()));
    $status = $tester->execute([]);
    $payload = Toon::decode($tester->getDisplay());

    expect($status)->toBe(0)
        ->and($payload['count'])->toBe(0)
        ->and($payload['sessions'])->toBe([])
        ->and($payload['message'])->toContain('No persisted sessions');
    expect($payload['help'] === [])->toBeFalse();
});

it('fails loudly when a requested list field is unknown', function (): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', 'agents', '--fields=missing'], $output);
    $payload = Toon::decode($output->fetch());

    expect($status)->toBe(2)
        ->and($payload['error'])->toContain('Unknown field(s): missing')
        ->and($payload['help'][0])->toContain('Valid flags for `agents`');
});

it('preserves explicit json for application-level errors', function (): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', '--output=json', '--bogus', 'hello'], $output);
    $payload = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(2)
        ->and($payload['error'])->toContain('The "--bogus" option does not exist');
    expect($payload['help'] === [])->toBeFalse();
});

it('exposes an honest typed map of tell operational planes', function (): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', 'planes', '--full'], $output);
    $payload = Toon::decode($output->fetch());
    $byCommand = [];
    foreach ($payload['operations'] as $operation) {
        $byCommand[$operation['command']] = $operation;
    }

    expect($status)->toBe(0)
        ->and($payload['systemBoundary'])->toContain('local Tell agent runtime')
        ->and($payload['separationLevel'])->toContain('one collocated process')
        ->and($payload['lastKnownGood'])->toContain('no persisted control snapshot')
            ->and($payload['planeCounts'])->toBe(['data' => 3, 'control' => 2, 'management' => 14])
        ->and($byCommand['tell "<prompt>"']['plane'])->toBe('data')
        ->and($byCommand['tool NAME JSON']['plane'])->toBe('data')
        ->and($byCommand['describe']['plane'])->toBe('control')
        ->and($byCommand['tools']['plane'])->toBe('control')
            ->and($byCommand['agents']['plane'])->toBe('management')
            ->and($byCommand['providers']['plane'])->toBe('management')
            ->and($byCommand['models']['plane'])->toBe('management')
        ->and($byCommand['auth']['plane'])->toBe('management')
        ->and($byCommand['branch']['plane'])->toBe('management')
        ->and($byCommand['clear']['authority'])->toContain('mutate only the selected ref')
        ->and($byCommand['compact [hint]']['authority'])->toContain('conditional selected-ref update')
        ->and($byCommand['init [path]']['plane'])->toBe('management')
        ->and($byCommand['sessions']['plane'])->toBe('management')
        ->and($byCommand['sessions']['authority'])->toContain('explicitly named session')
        ->and($byCommand['context']['authority'])->toContain('Read canonical state and configuration only')
        ->and($byCommand['history']['authority'])->toContain('Read verified canonical objects only')
        ->and($byCommand['transcript']['authority'])->toContain('Read verified canonical objects only')
        ->and($byCommand['tell "<prompt>"']['degradedBehavior'])->toContain('stateless turns');
});

it('keeps the default plane map compact', function (): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $output = new BufferedOutput;

    $status = $application->runArgv(['tell', 'planes'], $output);
    $payload = Toon::decode($output->fetch());

    expect($status)->toBe(0)
        ->and($payload['operations'][0])->toHaveKeys(['plane', 'command', 'responsibility']);
    expect(array_key_exists('ownedState', $payload['operations'][0]))->toBeFalse();
});
