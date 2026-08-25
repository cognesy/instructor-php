<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\Command\ToolCommand;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\Runtime\TellSignalCancellationSource;
use Cognesy\Tell\Runtime\TellToolDispatcher;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use HelgeSverre\Toon\Toon;
use Symfony\Component\Console\Tester\CommandTester;

it('invokes the resolved canonical tool directly without inference or workspace publication', function (): void {
    $recorder = new RequestRecorder;
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(new RecordingDriver($recorder)));
    $directory = tellLastTemporaryRoot();
    file_put_contents($directory.'/note.txt', "direct evidence\n");
    $tester = new CommandTester(new ToolCommand($factory));

    $status = $tester->execute([
        'name' => 'read_file',
        'input' => '{"path":"note.txt"}',
        '--dir' => $directory,
        '--output' => 'json',
    ]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and($payload['success'])->toBeTrue()
        ->and($payload['tool'])->toBe('read_file')
        ->and($payload['execution'])->toBe(['mode' => 'direct', 'inference' => false, 'durable' => false])
        ->and($payload['data']['text'])->toContain('direct evidence')
        ->and($recorder->requests)->toBe([])
        ->and(is_dir($directory.'/.tell'))->toBeFalse();
});

it('uses the exact resolved allow-list and strict tool schema', function (): void {
    $factory = tellTestFactory();
    $directory = tellLastTemporaryRoot();
    $tester = new CommandTester(new ToolCommand($factory));

    $disabled = $tester->execute([
        'name' => 'shell',
        'input' => '{"command":"printf blocked"}',
        '--dir' => $directory,
        '--tools' => 'read_file',
        '--output' => 'json',
    ]);
    $disabledPayload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    $invalid = $tester->execute([
        'name' => 'read_file',
        'input' => '{"path":"missing.txt","unknown":true}',
        '--dir' => $directory,
        '--output' => 'json',
    ]);
    $invalidPayload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($disabled)->toBe(1)
        ->and($disabledPayload['error']['code'])->toBe('tool_unavailable')
        ->and($invalid)->toBe(2)
        ->and($invalidPayload['error']['code'])->toBe('invalid_input')
        ->and($invalidPayload['error']['message'])->toContain('Unknown argument');
});

it('honours direct policy rejection and emits normalized payload-free events', function (): void {
    $factory = tellTestFactory();
    $directory = tellLastTemporaryRoot();
    $tester = new CommandTester(new ToolCommand($factory));

    $blocked = $tester->execute([
        'name' => 'shell',
        'input' => '{"command":"printf skipped"}',
        '--dir' => $directory,
        '--max-tool-calls' => '0',
        '--output' => 'json',
    ]);
    $blockedPayload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    $events = $tester->execute([
        'name' => 'shell',
        'input' => '{"command":"printf event-ok"}',
        '--dir' => $directory,
        '--output' => 'events',
    ]);
    $lines = array_values(array_filter(explode("\n", trim($tester->getDisplay()))));
    $event = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
    $terminal = json_decode($lines[2], true, flags: JSON_THROW_ON_ERROR);

    expect($blocked)->toBe(1)
        ->and($blockedPayload['error']['code'])->toBe('policy_rejected')
        ->and($events)->toBe(0)
        ->and($event['schema'])->toBe('tell.event.v1')
        ->and($event['kind'])->toBe('tool.started')
        ->and($event['metadata'])->toHaveKeys(['tool', 'effect'])
        ->and($terminal['terminal'])->toBe('completed');
    expect(json_encode($event, JSON_THROW_ON_ERROR))->not->toContain('event-ok');
});

it('rejects ambiguous and malformed argument sources with usage exit code two', function (): void {
    $factory = tellTestFactory();
    $tester = new CommandTester(new ToolCommand($factory));

    $ambiguous = $tester->execute([
        'name' => 'read_file',
        'input' => '{"path":"x"}',
        '--stdin' => true,
        '--dir' => tellLastTemporaryRoot(),
        '--output' => 'json',
    ]);
    $ambiguousPayload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    $malformed = $tester->execute([
        'name' => 'read_file',
        'input' => '{not-json}',
        '--dir' => tellLastTemporaryRoot(),
        '--output' => 'json',
    ]);
    $malformedPayload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($ambiguous)->toBe(2)
        ->and($ambiguousPayload['error']['code'])->toBe('invalid_input')
        ->and($malformed)->toBe(2)
        ->and($malformedPayload['error']['message'])->toBe('Tool arguments must be a valid JSON object.');
});

it('reports bounded timeouts and pre-cancelled direct work without inference', function (): void {
    $recorder = new RequestRecorder;
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(new RecordingDriver($recorder)));
    $directory = tellLastTemporaryRoot();
    $tester = new CommandTester(new ToolCommand($factory));

    $timeout = $tester->execute([
        'name' => 'shell',
        'input' => '{"command":"sleep 2"}',
        '--dir' => $directory,
        '--timeout-ms' => '1',
        '--output' => 'json',
    ]);
    $timeoutPayload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    $cancellation = new TellSignalCancellationSource;
    $cancellation->cancel();
    $cancelled = (new TellToolDispatcher($factory, $cancellation))->dispatch(
        new TellOptions(prompt: 'direct', directory: $directory),
        'shell',
        ['command' => 'printf never'],
    );

    expect($timeout)->toBe(1)
        ->and($timeoutPayload['error']['code'])->toBe('timeout')
        ->and($cancelled['error']['code'])->toBe('cancelled')
        ->and($recorder->requests)->toBe([]);
});
