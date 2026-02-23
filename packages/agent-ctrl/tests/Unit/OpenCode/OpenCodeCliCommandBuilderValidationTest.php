<?php declare(strict_types=1);

use Cognesy\AgentCtrl\OpenCode\Application\Builder\OpenCodeCommandBuilder;
use Cognesy\AgentCtrl\OpenCode\Application\Dto\OpenCodeRequest;

it('rejects unsupported characters in opencode model', function () {
    $request = new OpenCodeRequest(
        prompt: 'run checks',
        model: 'anthropic bad/model',
    );

    expect(fn() => (new OpenCodeCommandBuilder())->buildRun($request))
        ->toThrow(InvalidArgumentException::class, 'model contains unsupported characters');
});

it('rejects unsupported characters in opencode session id', function () {
    $request = new OpenCodeRequest(
        prompt: 'resume work',
        sessionId: 'session bad',
    );

    expect(fn() => (new OpenCodeCommandBuilder())->buildRun($request))
        ->toThrow(InvalidArgumentException::class, 'sessionId contains unsupported characters');
});

it('accepts safe opencode model and session id values', function () {
    $request = new OpenCodeRequest(
        prompt: 'resume work',
        model: 'anthropic/claude-sonnet-4-5',
        sessionId: 'session_abc-123:foo/bar',
    );

    $spec = (new OpenCodeCommandBuilder())->buildRun($request);
    $argv = $spec->argv()->toArray();

    expect($argv)->toContain('--model')
        ->and($argv)->toContain('anthropic/claude-sonnet-4-5')
        ->and($argv)->toContain('--session')
        ->and($argv)->toContain('session_abc-123:foo/bar');
});

it('rejects missing opencode file attachments', function () {
    $missingPath = sys_get_temp_dir() . '/opencode-missing-file-' . uniqid('', true) . '.txt';
    $request = new OpenCodeRequest(
        prompt: 'run checks',
        files: [$missingPath],
    );

    expect(fn() => (new OpenCodeCommandBuilder())->buildRun($request))
        ->toThrow(InvalidArgumentException::class, 'files[0] does not exist or is not a file');
});

it('accepts existing opencode file attachments', function () {
    $filePath = sys_get_temp_dir() . '/opencode-file-' . uniqid('', true) . '.txt';
    file_put_contents($filePath, 'sample');

    try {
        $request = new OpenCodeRequest(
            prompt: 'run checks',
            files: [$filePath],
        );

        $argv = (new OpenCodeCommandBuilder())->buildRun($request)->argv()->toArray();

        expect($argv)->toContain('--file')
            ->and($argv)->toContain($filePath);
    } finally {
        @unlink($filePath);
    }
});
