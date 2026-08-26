<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\Protocol\TellAgentProtocolRequest;
use Cognesy\Tell\Protocol\TellAgentProtocolWriter;

/**
 * @return array{code: int, output: string, errors: string, frames: list<array<string, mixed>>}
 */
function tellAgentProtocolProcess(
    string $project,
    string $input,
    string $scenario = 'success',
    ?string $composerVendorDir = null,
): array
{
    $environment = [
        'TELL_RPC_SCENARIO' => $scenario,
        'TELL_RPC_PROJECT' => $project,
    ];
    if ($composerVendorDir !== null) {
        $environment['TELL_RPC_COMPOSER_VENDOR_DIR'] = $composerVendorDir;
    }
    $process = proc_open(
        [
            PHP_BINARY,
            dirname(__DIR__).'/Fixtures/agent-protocol-worker.php',
            'agent',
            '--rpc',
            '--dir',
            $project,
        ],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 3),
        $environment,
    );
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the Tell agent protocol subprocess.');
    }

    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    $lines = array_values(array_filter(explode("\n", trim($output)), static fn (string $line): bool => $line !== ''));
    $frames = array_map(
        static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        $lines,
    );

    return ['code' => $code, 'output' => $output, 'errors' => $errors, 'frames' => $frames];
}

/** @param array<string, mixed> $overrides */
function tellAgentProtocolRequest(array $overrides = []): string
{
    return json_encode([
        'schema' => TellAgentProtocolRequest::SCHEMA,
        'id' => 'controller-1',
        'prompt' => 'prompt-secret-canary',
        ...$overrides,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
}

/** @param list<array<string, mixed>> $frames */
function expectTellAgentProtocolFrames(array $frames, string $terminalType): void
{
    expect($frames)->not->toBeEmpty();
    foreach ($frames as $index => $frame) {
        expect($frame['schema'])->toBe(TellAgentProtocolWriter::SCHEMA)
            ->and($frame['sequence'])->toBe($index + 1);
    }
    $terminals = array_values(array_filter(
        $frames,
        static fn (array $frame): bool => in_array($frame['type'], ['result', 'error', 'cancelled'], true),
    ));
    expect($terminals)->toHaveCount(1)
        ->and($frames[array_key_last($frames)]['type'])->toBe($terminalType);
}

it('streams ordered progress and exactly one successful terminal frame from a real process', function (): void {
    $project = tellTestProject();
    file_put_contents($project.'/protocol-evidence.txt', "bounded evidence\n");

    $run = tellAgentProtocolProcess($project, tellAgentProtocolRequest());

    expectTellAgentProtocolFrames($run['frames'], 'result');
    expect($run['code'])->toBe(0)
        ->and($run['errors'])->toBe('')
        ->and($run['frames'][0]['type'])->toBe('progress')
        ->and(rtrim($run['frames'][array_key_last($run['frames'])]['result']['answer']))->toBe('safe protocol result')
        ->and($run['output'])->not->toContain('prompt-secret-canary')
        ->and($run['output'])->not->toContain($project);
});

it('returns sanitized extension discovery diagnostics over the external protocol', function (): void {
    $project = tellTestProject();
    $vendor = tellMalformedComposerVendor();
    $run = tellAgentProtocolProcess($project, tellAgentProtocolRequest(), composerVendorDir: $vendor);

    expectTellAgentProtocolFrames($run['frames'], 'result');
    $diagnostics = $run['frames'][array_key_last($run['frames'])]['result']['diagnostics'];
    expect($run['code'])->toBe(0)
        ->and($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]['code'])->toBe('extension_discovery_error')
        ->and($diagnostics[0]['message'])->toContain('inspect local Tell diagnostics')
        ->and($run['output'])->not->toContain($vendor)
        ->and($run['output'])->not->toContain('example/malformed-extension');
});

it('rejects malformed input and unknown protocol versions without inference', function (string $input, string $code): void {
    $project = tellTestProject();
    $run = tellAgentProtocolProcess($project, $input, 'failure');

    expectTellAgentProtocolFrames($run['frames'], 'error');
    expect($run['code'])->toBe(2)
        ->and($run['errors'])->toBe('')
        ->and($run['frames'])->toHaveCount(1)
        ->and($run['frames'][0]['error']['code'])->toBe($code)
        ->and($run['output'])->not->toContain('provider-secret-canary');
})->with([
    'malformed json' => ['{"schema":', 'invalid_request'],
    'unknown version' => [tellAgentProtocolRequest(['schema' => 'tell.agent.request.v999']), 'unsupported_version'],
    'multiple lines' => [tellAgentProtocolRequest().tellAgentProtocolRequest(), 'invalid_request'],
]);

it('returns a distinct sanitized failure terminal from a real process', function (): void {
    $project = tellTestProject();
    $run = tellAgentProtocolProcess($project, tellAgentProtocolRequest(), 'failure');

    expectTellAgentProtocolFrames($run['frames'], 'error');
    expect($run['code'])->toBe(1)
        ->and($run['frames'][array_key_last($run['frames'])]['error']['code'])->toBe('run_failed')
        ->and($run['output'])->not->toContain('provider-secret-canary')
        ->and($run['output'])->not->toContain('prompt-secret-canary')
        ->and($run['errors'])->toBe('');
});

it('returns a distinct sanitized cancellation terminal and exit status', function (): void {
    $project = tellTestProject();
    $run = tellAgentProtocolProcess($project, tellAgentProtocolRequest(), 'cancelled');

    expectTellAgentProtocolFrames($run['frames'], 'cancelled');
    expect($run['code'])->toBe(130)
        ->and($run['frames'][array_key_last($run['frames'])]['cancellation']['code'])->toBe('cancelled')
        ->and($run['output'])->not->toContain('cancellation-secret-canary')
        ->and($run['errors'])->toBe('');
});

it('enforces bounded input and output frames', function (): void {
    $project = tellTestProject();
    $oversized = tellAgentProtocolProcess(
        $project,
        str_repeat('x', TellAgentProtocolRequest::MAX_INPUT_BYTES + 1),
    );
    $large = tellAgentProtocolProcess($project, tellAgentProtocolRequest([
        'policy' => ['maxOutputChars' => 1_000_000],
    ]), 'large');

    expectTellAgentProtocolFrames($oversized['frames'], 'error');
    expectTellAgentProtocolFrames($large['frames'], 'result');
    $result = $large['frames'][array_key_last($large['frames'])]['result'];
    expect($oversized['code'])->toBe(2)
        ->and($oversized['frames'][0]['error']['code'])->toBe('input_limit')
        ->and($result['answerTruncated'])->toBeTrue()
        ->and($result['answerBytes'])->toBe(200_000);
    foreach (explode("\n", trim($large['output'])) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(TellAgentProtocolWriter::MAX_FRAME_BYTES);
    }
});
