<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Adapter\Console\Symfony\TellOptions;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellPublicationStatus;
use Cognesy\Tell\Core\Observation\TellEventNormalizer;
use Cognesy\Tell\Adapter\Console\Symfony\TellSignalCancellationSource;
use Cognesy\Tell\Tests\Support\TestAutoload;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemArena;

it('preserves default, empty, and exact Tell tool selections', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromResponses('done'),
    ));
    $project = tellLastTemporaryRoot() . '/tool-selection';
    mkdir($project, 0700, true);

    $defaults = $factory->build(TellRequest::prompt('defaults')->withDirectory($project));
    $none = $factory->build(TellRequest::prompt('none')->withDirectory($project)->tools([]));
    $configuredNone = $factory->build(
        TellRequest::prompt('configured none')->withDirectory($project)->withBranchConfig(['tools' => []]),
    );
    $readOnly = $factory->build(TellRequest::prompt('read')->withDirectory($project)->tools(['read_file']));

    expect($defaults->tools()->names())->not->toBeEmpty()
        ->and($none->tools()->names())->toBe([])
        ->and($configuredNone->tools()->names())->toBe([])
        ->and($readOnly->tools()->names())->toBe(['read_file']);

    expect(fn () => $factory->build(
        TellRequest::prompt('unknown')->withDirectory($project)->tools(['missing_tool']),
    ))->toThrow(InvalidArgumentException::class, 'Unknown Tell tool(s): missing_tool.');
});

it('reports the active post-filter tool count when a step starts', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(
        FakeAgentDriver::fromResponses('done'),
    ));
    $project = tellLastTemporaryRoot() . '/tool-event';
    mkdir($project, 0700, true);
    $events = [];
    $normalizer = new TellEventNormalizer();
    $loop = $factory->build(
        TellRequest::prompt('read')->withDirectory($project)->tools(['read_file']),
    );
    $loop->wiretap(static function (object $event) use (&$events, $normalizer): void {
        $events[] = $normalizer->normalize($event);
    });
    $loop->execute(AgentState::empty()->withUserMessage('read'));

    $started = array_values(array_filter(
        $events,
        static fn (array $event): bool => $event['kind'] === 'step.started',
    ));
    expect($started)->toHaveCount(1)
        ->and($started[0]['metadata']['tools'])->toBe(1);
});

it('cooperatively cancels a durable execution before inference and does not publish', function (): void {
    $source = new InMemoryCancellationSource();
    $source->cancel('secret cancellation canary');
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/cancelled-workspace';
    mkdir($project, 0700, true);
    $workspace = tellTestWorkspaces()->initialize($project)->workspace;
    $events = [];
    $normalizer = new TellEventNormalizer(branch: 'main');
    $loop = $factory->build(
        (new TellOptions(prompt: 'Do not infer', directory: $project))->request(),
        cancellation: $source,
    );
    $loop->wiretap(static function (object $event) use (&$events, $normalizer): void {
        $events[] = $normalizer->normalize($event);
    });
    $loop->execute(AgentState::empty()->withUserMessage('Do not infer'));

    $result = tellTestOpen($project, $factory, $source)->run(
        TellRequest::prompt('Do not infer')->durable(),
    );

    $terminal = array_values(array_filter($events, static fn (array $event): bool => $event['terminal'] !== null));
    expect($result->termination()->stopSignal?->reason)->toBe(StopReason::UserRequested)
        ->and($result->publication()->status)->toBe(TellPublicationStatus::NotAttempted)
        ->and($terminal)->toHaveCount(1)
        ->and($terminal[0]['terminal'])->toBe('stopped')
        ->and((new FilesystemArena($workspace))->readRef('main')->head)->toBeNull()
        ->and(json_encode($events, JSON_THROW_ON_ERROR))->not->toContain('secret cancellation canary');
});

it('receives SIGINT in a short subprocess when pcntl is available', function (): void {
    if (!TellSignalCancellationSource::isSupported() || !function_exists('posix_kill')) {
        expect(TellSignalCancellationSource::isSupported())->toBeFalse();

        return;
    }
    $script = <<<'PHP'
require $argv[1];
$source = new \Cognesy\Tell\Adapter\Console\Symfony\TellSignalCancellationSource();
$source->install();
echo "ready\n";
flush();
$state = \Cognesy\Agents\Data\AgentState::empty();
for ($i = 0; $i < 300; $i++) {
    if ($source->cancellationSignal($state) !== null) {
        echo "cancelled\n";
        exit(0);
    }
    usleep(10000);
}
exit(2);
PHP;
    $process = proc_open([PHP_BINARY, '-r', $script, TestAutoload::path()], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start SIGINT test subprocess.');
    }
    expect(trim((string) fgets($pipes[1])))->toBe('ready');
    $status = proc_get_status($process);
    posix_kill($status['pid'], SIGINT);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    expect(proc_close($process))->toBe(0)
        ->and($output)->toContain('cancelled')
        ->and($errors)->toBe('');
});
