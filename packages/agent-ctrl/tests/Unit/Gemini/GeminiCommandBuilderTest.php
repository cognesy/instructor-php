<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Gemini\Application\Builder\GeminiCommandBuilder;
use Cognesy\AgentCtrl\Gemini\Application\Dto\GeminiRequest;
use Cognesy\AgentCtrl\Gemini\Domain\Enum\ApprovalMode;

it('builds basic gemini command with stream-json output format', function () {
    $request = new GeminiRequest(prompt: 'hello world');
    $spec = (new GeminiCommandBuilder())->build($request);
    $argv = $spec->argv()->toArray();

    expect($argv)->toContain('gemini')
        ->and($argv)->toContain('--output-format')
        ->and($argv)->toContain('stream-json')
        ->and($argv)->toContain('--prompt')
        ->and($argv)->toContain('hello world');
});

it('builds gemini command with model flag', function () {
    $request = new GeminiRequest(prompt: 'test', model: 'flash');
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--model')
        ->and($argv)->toContain('flash');
});

it('builds gemini command with full model name', function () {
    $request = new GeminiRequest(prompt: 'test', model: 'gemini-2.5-pro');
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--model')
        ->and($argv)->toContain('gemini-2.5-pro');
});

it('builds gemini command with approval mode', function () {
    $request = new GeminiRequest(prompt: 'test', approvalMode: ApprovalMode::Yolo);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--approval-mode')
        ->and($argv)->toContain('yolo');
});

it('builds gemini command with plan mode', function () {
    $request = new GeminiRequest(prompt: 'test', approvalMode: ApprovalMode::Plan);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--approval-mode')
        ->and($argv)->toContain('plan');
});

it('builds gemini command with auto_edit mode', function () {
    $request = new GeminiRequest(prompt: 'test', approvalMode: ApprovalMode::AutoEdit);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--approval-mode')
        ->and($argv)->toContain('auto_edit');
});

it('builds gemini command with sandbox flag', function () {
    $request = new GeminiRequest(prompt: 'test', sandbox: true);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--sandbox');
});

it('omits sandbox flag when false', function () {
    $request = new GeminiRequest(prompt: 'test', sandbox: false);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->not->toContain('--sandbox');
});

it('builds gemini command with include directories', function () {
    $request = new GeminiRequest(prompt: 'test', includeDirectories: ['/path/a', '/path/b']);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    // Each directory should be preceded by --include-directories
    $indices = array_keys(array_filter($argv, fn($v) => $v === '--include-directories'));
    expect(count($indices))->toBe(2)
        ->and($argv)->toContain('/path/a')
        ->and($argv)->toContain('/path/b');
});

it('builds gemini command with extensions', function () {
    $request = new GeminiRequest(prompt: 'test', extensions: ['ext1', 'ext2']);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    $indices = array_keys(array_filter($argv, fn($v) => $v === '--extensions'));
    expect(count($indices))->toBe(2)
        ->and($argv)->toContain('ext1')
        ->and($argv)->toContain('ext2');
});

it('builds gemini command with allowed tools', function () {
    $request = new GeminiRequest(prompt: 'test', allowedTools: ['read_file', 'search_files']);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    $indices = array_keys(array_filter($argv, fn($v) => $v === '--allowed-tools'));
    expect(count($indices))->toBe(2)
        ->and($argv)->toContain('read_file')
        ->and($argv)->toContain('search_files');
});

it('builds gemini command with allowed mcp server names', function () {
    $request = new GeminiRequest(prompt: 'test', allowedMcpServerNames: ['filesystem', 'github']);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    $indices = array_keys(array_filter($argv, fn($v) => $v === '--allowed-mcp-server-names'));
    expect(count($indices))->toBe(2)
        ->and($argv)->toContain('filesystem')
        ->and($argv)->toContain('github');
});

it('builds gemini command with policy paths', function () {
    $request = new GeminiRequest(prompt: 'test', policy: ['/path/to/policy.yaml']);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--policy')
        ->and($argv)->toContain('/path/to/policy.yaml');
});

it('builds gemini command with resume session', function () {
    $request = new GeminiRequest(prompt: 'test', resumeSession: 'latest');
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--resume')
        ->and($argv)->toContain('latest');
});

it('builds gemini command with resume session by id', function () {
    $request = new GeminiRequest(prompt: 'test', resumeSession: 'abc-123-def');
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--resume')
        ->and($argv)->toContain('abc-123-def');
});

it('builds gemini command with debug flag', function () {
    $request = new GeminiRequest(prompt: 'test', debug: true);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--debug');
});

it('omits debug flag when false', function () {
    $request = new GeminiRequest(prompt: 'test', debug: false);
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->not->toContain('--debug');
});

it('omits optional flags when not set', function () {
    $request = new GeminiRequest(prompt: 'test');
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->not->toContain('--model')
        ->and($argv)->not->toContain('--approval-mode')
        ->and($argv)->not->toContain('--sandbox')
        ->and($argv)->not->toContain('--include-directories')
        ->and($argv)->not->toContain('--extensions')
        ->and($argv)->not->toContain('--allowed-tools')
        ->and($argv)->not->toContain('--allowed-mcp-server-names')
        ->and($argv)->not->toContain('--policy')
        ->and($argv)->not->toContain('--resume')
        ->and($argv)->not->toContain('--debug');
});

it('builds gemini command with all flags combined', function () {
    $request = new GeminiRequest(
        prompt: 'review this code',
        model: 'pro',
        approvalMode: ApprovalMode::AutoEdit,
        sandbox: true,
        includeDirectories: ['/extra'],
        extensions: ['my-ext'],
        allowedTools: ['read_file'],
        policy: ['/policy.yaml'],
        resumeSession: 'latest',
        debug: true,
    );
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--output-format')
        ->and($argv)->toContain('stream-json')
        ->and($argv)->toContain('--model')
        ->and($argv)->toContain('pro')
        ->and($argv)->toContain('--approval-mode')
        ->and($argv)->toContain('auto_edit')
        ->and($argv)->toContain('--sandbox')
        ->and($argv)->toContain('--include-directories')
        ->and($argv)->toContain('/extra')
        ->and($argv)->toContain('--extensions')
        ->and($argv)->toContain('my-ext')
        ->and($argv)->toContain('--allowed-tools')
        ->and($argv)->toContain('read_file')
        ->and($argv)->toContain('--policy')
        ->and($argv)->toContain('/policy.yaml')
        ->and($argv)->toContain('--resume')
        ->and($argv)->toContain('latest')
        ->and($argv)->toContain('--debug')
        ->and($argv)->toContain('--prompt')
        ->and($argv)->toContain('review this code');
});

it('rejects empty prompt', function () {
    $request = new GeminiRequest(prompt: '');
    (new GeminiCommandBuilder())->build($request);
})->throws(InvalidArgumentException::class, 'Prompt must not be empty');

it('rejects whitespace-only prompt', function () {
    $request = new GeminiRequest(prompt: '   ');
    (new GeminiCommandBuilder())->build($request);
})->throws(InvalidArgumentException::class, 'Prompt must not be empty');

it('prompt is passed via --prompt flag', function () {
    $request = new GeminiRequest(prompt: 'What is 2+2?');
    $argv = (new GeminiCommandBuilder())->build($request)->argv()->toArray();

    $promptIndex = array_search('--prompt', $argv);
    expect($promptIndex)->not->toBeFalse()
        ->and($argv[$promptIndex + 1])->toBe('What is 2+2?');
});
