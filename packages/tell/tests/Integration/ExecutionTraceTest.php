<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\TellCommand;
use HelgeSverre\Toon\Toon;
use Symfony\Component\Console\Tester\CommandTester;

it('writes one private jsonl trace from the real execution event stream', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('traced answer')));
    $tester = new CommandTester(new TellCommand($factory));

    $status = $tester->execute(['prompt' => 'private prompt']);
    $files = tellTraceFiles($factory->paths()->executionTraces);
    $records = tellTraceRecords($files[0] ?? '');

    expect($status)->toBe(0)
        ->and(Toon::decode($tester->getDisplay())['answer'])->toBe('traced answer')
        ->and($files)->toHaveCount(1)
        ->and($records[0]['schema'])->toBe('tell.event.v1')
        ->and($records[0]['kind'])->toBe('execution.started')
        ->and($records[0]['session'])->toBeNull()
        ->and($records[array_key_last($records)]['kind'])->toBe('execution.completed');
    expect(array_unique(array_column($records, 'executionId')))->toHaveCount(1);
    if (DIRECTORY_SEPARATOR !== '\\') {
        expect(fileperms($files[0]) & 0777)->toBe(0600);
    }
});

it('includes trace payloads only when explicitly enabled in local config', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('done')));
    file_put_contents($factory->paths()->configFile, json_encode([
        'schema' => 'tell.config.v1',
        'observability' => ['includePayloads' => true],
    ], JSON_THROW_ON_ERROR));

    (new CommandTester(new TellCommand($factory)))->execute(['prompt' => 'captured prompt']);
    $records = tellTraceRecords(tellTraceFiles($factory->paths()->executionTraces)[0] ?? '');

    expect($records[0]['payload']['messagePayload'])->toBeArray();
    expect(json_encode($records[0]['payload']['messagePayload'], JSON_THROW_ON_ERROR))->toContain('captured prompt');
});

it('allows execution traces to be disabled in local config', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('done')));
    file_put_contents($factory->paths()->configFile, json_encode([
        'schema' => 'tell.config.v1',
        'observability' => ['executionTraces' => false],
    ], JSON_THROW_ON_ERROR));

    $status = (new CommandTester(new TellCommand($factory)))->execute(['prompt' => 'not traced']);

    expect($status)->toBe(0)
        ->and(is_dir($factory->paths()->executionTraces))->toBeFalse();
});

it('marks transient traces without widening the existing redaction policy', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('done')));
    $tester = new CommandTester(new TellCommand($factory));

    expect($tester->execute(['prompt' => 'transient trace prompt', '--transient' => true]))->toBe(0);
    $records = tellTraceRecords(tellTraceFiles($factory->paths()->executionTraces)[0] ?? '');

    expect($records[array_key_last($records)]['terminal'])->toBe('completed')
        ->and(json_encode($records, JSON_THROW_ON_ERROR))->not->toContain('transient trace prompt');
});

it('fails before inference when explicit observability config is invalid', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('must not run')));
    file_put_contents($factory->paths()->configFile, '{"observability":{"trace":true}}');
    $tester = new CommandTester(new TellCommand($factory));

    $status = $tester->execute(['prompt' => 'invalid config']);
    $payload = Toon::decode($tester->getDisplay());

    expect($status)->toBe(1)
        ->and($payload['error'])->toContain('unknown key(s): trace')
        ->and(is_dir($factory->paths()->logs))->toBeFalse();
});

it('uses a separate lock-safe trace file for every named session', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('done')));
    $command = new TellCommand($factory);

    (new CommandTester($command))->execute(['prompt' => 'alpha turn', '--session' => 'alpha']);
    (new CommandTester($command))->execute(['prompt' => 'beta turn', '--session' => 'beta']);
    $files = glob($factory->paths()->sessionTraces.'/*.jsonl') ?: [];
    sort($files);
    $sessions = array_map(
        static fn (string $file): mixed => tellTraceRecords($file)[0]['session'] ?? null,
        $files,
    );

    expect($files)->toHaveCount(2)
        ->and($sessions)->toBe(['alpha', 'beta']);
    expect(basename($files[0]))->toStartWith('alpha-');
    expect(basename($files[1]))->toStartWith('beta-');
});

it('appends later turns to the same named session trace', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('done')));
    $command = new TellCommand($factory);

    (new CommandTester($command))->execute(['prompt' => 'first', '--session' => 'review']);
    (new CommandTester($command))->execute(['prompt' => 'second', '--session' => 'review']);
    $files = glob($factory->paths()->sessionTraces.'/*.jsonl') ?: [];
    $records = tellTraceRecords($files[0] ?? '');

    expect($files)->toHaveCount(1)
        ->and(array_count_values(array_column($records, 'kind'))['execution.started'] ?? 0)->toBe(2);
});

it('does not fail an agent turn when trace storage is unavailable', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('still works')));
    file_put_contents($factory->paths()->logs, 'blocks the logs directory');
    $tester = new CommandTester(new TellCommand($factory));

    $status = $tester->execute(['prompt' => 'trace failure']);

    expect($status)->toBe(0)
        ->and(Toon::decode($tester->getDisplay())['answer'])->toBe('still works');
});

/** @return list<string> */
function tellTraceFiles(string $directory): array
{
    $files = glob($directory.'/*/*.jsonl') ?: [];
    sort($files);

    return array_values($files);
}

/** @return list<array<string, mixed>> */
function tellTraceRecords(string $path): array
{
    $contents = file_get_contents($path);
    if (! is_string($contents)) {
        return [];
    }
    $lines = array_values(array_filter(explode("\n", $contents)));

    return array_map(
        static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        $lines,
    );
}
