<?php

declare(strict_types=1);

use Cognesy\Auxiliary\ClaudeCodeCli\Application\Builder\ClaudeCommandBuilder;
use Cognesy\Auxiliary\ClaudeCodeCli\Application\Dto\ClaudeRequest;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\InputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\PermissionMode;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Value\PathList;

it('builds default headless argv', function () {
    $request = new ClaudeRequest(
        prompt: 'hello',
        outputFormat: OutputFormat::Text,
    );
    $spec = (new ClaudeCommandBuilder())->buildHeadless($request);

    expect($spec->argv()->toArray())->toBe([
        'claude',
        '-p',
        'hello',
        '--output-format',
        'text',
    ]);
});

it('builds headless argv with options', function () {
    $request = new ClaudeRequest(
        prompt: 'run tests',
        outputFormat: OutputFormat::Json,
        permissionMode: PermissionMode::Plan,
        maxTurns: 3,
        model: 'claude-sonnet-4-5-20250929',
        systemPromptFile: '/tmp/system.txt',
        appendSystemPrompt: 'extra',
        agentsJson: '{"reviewer":{"description":"Review","prompt":"Review code"}}',
        additionalDirs: PathList::of(['../shared', '/tmp/data']),
    );

    $spec = (new ClaudeCommandBuilder())->buildHeadless($request);

    expect($spec->argv()->toArray())->toBe([
        'claude',
        '-p',
        'run tests',
        '--permission-mode',
        'plan',
        '--output-format',
        'json',
        '--max-turns',
        '3',
        '--model',
        'claude-sonnet-4-5-20250929',
        '--system-prompt-file',
        '/tmp/system.txt',
        '--append-system-prompt',
        'extra',
        '--agents',
        '{"reviewer":{"description":"Review","prompt":"Review code"}}',
        '--add-dir',
        '../shared',
        '--add-dir',
        '/tmp/data',
    ]);
});

it('builds with streaming flags, verbose, and permissions tooling', function () {
    $request = new ClaudeRequest(
        prompt: 'analyze logs',
        outputFormat: OutputFormat::StreamJson,
        permissionMode: PermissionMode::Plan,
        includePartialMessages: true,
        inputFormat: InputFormat::StreamJson,
        verbose: true,
        permissionPromptTool: 'mcp__auth__prompt',
        dangerouslySkipPermissions: false,
        continueMostRecent: true,
        stdin: '{"type":"user","message":{"role":"user","content":[{"type":"text","text":"hello"}]}}',
    );

    $spec = (new ClaudeCommandBuilder())->buildHeadless($request);

    expect($spec->argv()->toArray())->toBe([
        'claude',
        '--continue',
        '-p',
        'analyze logs',
        '--permission-mode',
        'plan',
        '--output-format',
        'stream-json',
        '--include-partial-messages',
        '--input-format',
        'stream-json',
        '--verbose',
        '--permission-prompt-tool',
        'mcp__auth__prompt',
    ]);
    expect($spec->stdin())->toBe('{"type":"user","message":{"role":"user","content":[{"type":"text","text":"hello"}]}}');
});

it('builds resume session with dangerous permissions skip', function () {
    $request = new ClaudeRequest(
        prompt: 'continue work',
        outputFormat: OutputFormat::Text,
        permissionMode: PermissionMode::DefaultMode,
        resumeSessionId: 'abc123',
        dangerouslySkipPermissions: true,
    );

    $spec = (new ClaudeCommandBuilder())->buildHeadless($request);

    expect($spec->argv()->toArray())->toBe([
        'claude',
        '--resume',
        'abc123',
        '-p',
        'continue work',
        '--output-format',
        'text',
        '--dangerously-skip-permissions',
    ]);
});

it('validates conflicting options', function () {
    $builder = new ClaudeCommandBuilder();

    expect(fn () => $builder->buildHeadless(new ClaudeRequest(
        prompt: '',
        outputFormat: OutputFormat::Text,
    )))->toThrow(InvalidArgumentException::class);

    expect(fn () => $builder->buildHeadless(new ClaudeRequest(
        prompt: 'x',
        outputFormat: OutputFormat::Json,
        systemPrompt: 'a',
        systemPromptFile: '/tmp/prompt.txt',
    )))->toThrow(InvalidArgumentException::class);

    expect(fn () => $builder->buildHeadless(new ClaudeRequest(
        prompt: 'x',
        outputFormat: OutputFormat::Text,
        includePartialMessages: true,
    )))->toThrow(InvalidArgumentException::class);

    expect(fn () => $builder->buildHeadless(new ClaudeRequest(
        prompt: 'x',
        outputFormat: OutputFormat::Json,
        inputFormat: InputFormat::StreamJson,
    )))->toThrow(InvalidArgumentException::class);

    expect(fn () => $builder->buildHeadless(new ClaudeRequest(
        prompt: 'x',
        outputFormat: OutputFormat::StreamJson,
        continueMostRecent: true,
        resumeSessionId: 'abc',
    )))->toThrow(InvalidArgumentException::class);
});
