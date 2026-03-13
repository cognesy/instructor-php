<?php declare(strict_types=1);

use Cognesy\AgentCtrl\OpenAICodex\Application\Builder\CodexCommandBuilder;
use Cognesy\AgentCtrl\OpenAICodex\Application\Dto\CodexRequest;

it('rejects unsupported characters in codex model', function () {
    $request = new CodexRequest(
        prompt: 'run checks',
        model: 'gpt bad',
    );

    expect(fn() => (new CodexCommandBuilder())->buildExec($request))
        ->toThrow(InvalidArgumentException::class, 'model contains unsupported characters');
});

it('rejects unsupported characters in codex resume session id', function () {
    $request = new CodexRequest(
        prompt: 'resume work',
        resumeSessionId: 'session bad',
    );

    expect(fn() => (new CodexCommandBuilder())->buildExec($request))
        ->toThrow(InvalidArgumentException::class, 'resumeSessionId contains unsupported characters');
});

it('accepts safe codex model and session id values', function () {
    $request = new CodexRequest(
        prompt: 'resume work',
        model: 'gpt-5-codex',
        resumeSessionId: 'session_abc-123:foo/bar',
    );

    $spec = (new CodexCommandBuilder())->buildExec($request);
    $argv = $spec->argv()->toArray();

    expect($argv)->toContain('--model')
        ->and($argv)->toContain('gpt-5-codex')
        ->and($argv)->toContain('resume')
        ->and($argv)->toContain('session_abc-123:foo/bar');
});

it('rejects missing codex image files', function () {
    $missingPath = sys_get_temp_dir() . '/codex-missing-image-' . uniqid('', true) . '.png';
    $request = new CodexRequest(
        prompt: 'run checks',
        images: [$missingPath],
    );

    expect(fn() => (new CodexCommandBuilder())->buildExec($request))
        ->toThrow(InvalidArgumentException::class, 'images[0] does not exist or is not a file');
});

it('rejects missing codex output schema file relative to working directory', function () {
    $workingDirectory = sys_get_temp_dir() . '/codex-wd-' . uniqid('', true);
    mkdir($workingDirectory);

    try {
        $request = new CodexRequest(
            prompt: 'run checks',
            workingDirectory: $workingDirectory,
            outputSchemaFile: 'schema.json',
        );

        expect(fn() => (new CodexCommandBuilder())->buildExec($request))
            ->toThrow(InvalidArgumentException::class, 'outputSchemaFile does not exist or is not a file');
    } finally {
        @rmdir($workingDirectory);
    }
});

it('accepts codex relative image and schema files when working directory is set', function () {
    $workingDirectory = sys_get_temp_dir() . '/codex-wd-' . uniqid('', true);
    mkdir($workingDirectory);
    file_put_contents($workingDirectory . '/image.png', 'fake-image');
    file_put_contents($workingDirectory . '/schema.json', '{"type":"object"}');

    try {
        $request = new CodexRequest(
            prompt: 'run checks',
            workingDirectory: $workingDirectory,
            images: ['image.png'],
            outputSchemaFile: 'schema.json',
        );

        $argv = (new CodexCommandBuilder())->buildExec($request)->argv()->toArray();

        expect($argv)->toContain('--image')
            ->and($argv)->toContain('image.png')
            ->and($argv)->toContain('--output-schema')
            ->and($argv)->toContain('schema.json');
    } finally {
        @unlink($workingDirectory . '/image.png');
        @unlink($workingDirectory . '/schema.json');
        @rmdir($workingDirectory);
    }
});
