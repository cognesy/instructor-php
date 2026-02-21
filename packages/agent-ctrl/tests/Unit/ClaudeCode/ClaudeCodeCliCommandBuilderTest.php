<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\ClaudeCode\Application\Builder\ClaudeCommandBuilder;
use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\InputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\AgentCtrl\Common\Value\PathList;

beforeEach(function () {
    $this->previousStdbuf = getenv('COGNESY_STDBUF');
    putenv('COGNESY_STDBUF=1');
});

afterEach(function () {
    $prev = $this->previousStdbuf;
    if ($prev === false || $prev === null) {
        putenv('COGNESY_STDBUF');
        return;
    }
    putenv('COGNESY_STDBUF=' . $prev);
});

it('builds default headless argv', function () {
    $request = new ClaudeRequest(
        prompt: 'hello',
        outputFormat: OutputFormat::Text,
    );
    $spec = (new ClaudeCommandBuilder())->buildHeadless($request);

    expect($spec->argv()->toArray())->toBe([
        'stdbuf',
        '-o0',
        'claude',
        '-p',
        'hello',
        '--output-format',
        'text',
    ]);
});

it('builds headless argv with options', function () {
    $systemPromptFile = tempnam(sys_get_temp_dir(), 'claude-system-');
    file_put_contents($systemPromptFile, 'system');
    $dirA = sys_get_temp_dir() . '/claude-dir-a-' . uniqid('', true);
    $dirB = sys_get_temp_dir() . '/claude-dir-b-' . uniqid('', true);
    mkdir($dirA);
    mkdir($dirB);

    $request = new ClaudeRequest(
        prompt: 'run tests',
        outputFormat: OutputFormat::Json,
        permissionMode: PermissionMode::Plan,
        maxTurns: 3,
        model: 'claude-sonnet-4-5-20250929',
        systemPromptFile: $systemPromptFile,
        appendSystemPrompt: 'extra',
        agentsJson: '{"reviewer":{"description":"Review","prompt":"Review code"}}',
        additionalDirs: PathList::of([$dirA, $dirB]),
    );

    $spec = (new ClaudeCommandBuilder())->buildHeadless($request);

    expect($spec->argv()->toArray())->toBe([
        'stdbuf',
        '-o0',
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
        $systemPromptFile,
        '--append-system-prompt',
        'extra',
        '--agents',
        '{"reviewer":{"description":"Review","prompt":"Review code"}}',
        '--add-dir',
        $dirA,
        '--add-dir',
        $dirB,
    ]);

    unlink($systemPromptFile);
    rmdir($dirA);
    rmdir($dirB);
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
        'stdbuf',
        '-o0',
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
        'stdbuf',
        '-o0',
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

it('validates advanced option formats and paths', function () {
    $builder = new ClaudeCommandBuilder();
    $missingFile = sys_get_temp_dir() . '/missing-prompt-' . uniqid('', true) . '.txt';
    $missingDir = sys_get_temp_dir() . '/missing-dir-' . uniqid('', true);

    expect(fn () => $builder->buildHeadless(new ClaudeRequest(
        prompt: 'x',
        outputFormat: OutputFormat::Text,
        systemPromptFile: $missingFile,
    )))->toThrow(InvalidArgumentException::class, 'systemPromptFile does not exist');

    expect(fn () => $builder->buildHeadless(new ClaudeRequest(
        prompt: 'x',
        outputFormat: OutputFormat::Text,
        agentsJson: '{"bad":',
    )))->toThrow(InvalidArgumentException::class, 'agentsJson must be valid JSON');

    expect(fn () => $builder->buildHeadless(new ClaudeRequest(
        prompt: 'x',
        outputFormat: OutputFormat::Text,
        additionalDirs: PathList::of([$missingDir]),
    )))->toThrow(InvalidArgumentException::class, 'additionalDirs entry is not an existing directory');

    expect(fn () => $builder->buildHeadless(new ClaudeRequest(
        prompt: 'x',
        outputFormat: OutputFormat::Text,
        model: 'bad model',
    )))->toThrow(InvalidArgumentException::class, 'model contains unsupported characters');

    expect(fn () => $builder->buildHeadless(new ClaudeRequest(
        prompt: 'x',
        outputFormat: OutputFormat::Text,
        resumeSessionId: 'bad session',
    )))->toThrow(InvalidArgumentException::class, 'resumeSessionId contains unsupported characters');
});

it('builds request with ClaudeRequestBuilder', function () {
    $request = ClaudeRequest::builder()
        ->withPrompt('builder prompt')
        ->withOutputFormat(OutputFormat::StreamJson)
        ->withPermissionMode(PermissionMode::Plan)
        ->withMaxTurns(2)
        ->withModel('claude-sonnet-4-5-20250929')
        ->withIncludePartialMessages(true)
        ->withContinueMostRecent(true)
        ->build();

    expect($request->prompt())->toBe('builder prompt');
    expect($request->outputFormat())->toBe(OutputFormat::StreamJson);
    expect($request->permissionMode())->toBe(PermissionMode::Plan);
    expect($request->maxTurns())->toBe(2);
    expect($request->model())->toBe('claude-sonnet-4-5-20250929');
    expect($request->includePartialMessages())->toBeTrue();
    expect($request->continueMostRecent())->toBeTrue();
});

it('builds argv without stdbuf when disabled', function () {
    putenv('COGNESY_STDBUF=0');

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
