<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Pi\Application\Builder\PiCommandBuilder;
use Cognesy\AgentCtrl\Pi\Application\Dto\PiRequest;
use Cognesy\AgentCtrl\Pi\Domain\Enum\OutputMode;
use Cognesy\AgentCtrl\Pi\Domain\Enum\ThinkingLevel;

it('builds basic pi command with json mode', function () {
    $request = new PiRequest(prompt: 'hello world');
    $spec = (new PiCommandBuilder())->build($request);
    $argv = $spec->argv()->toArray();

    // Should contain pi binary, --mode json, and prompt
    expect($argv)->toContain('pi')
        ->and($argv)->toContain('--mode')
        ->and($argv)->toContain('json')
        ->and(end($argv))->toBe('hello world');
});

it('builds pi command with model flag', function () {
    $request = new PiRequest(prompt: 'test', model: 'sonnet:high');
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--model')
        ->and($argv)->toContain('sonnet:high');
});

it('builds pi command with provider flag', function () {
    $request = new PiRequest(prompt: 'test', provider: 'anthropic');
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--provider')
        ->and($argv)->toContain('anthropic');
});

it('builds pi command with thinking level', function () {
    $request = new PiRequest(prompt: 'test', thinkingLevel: ThinkingLevel::High);
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--thinking')
        ->and($argv)->toContain('high');
});

it('builds pi command with system prompt', function () {
    $request = new PiRequest(prompt: 'test', systemPrompt: 'You are a helpful assistant');
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--system-prompt')
        ->and($argv)->toContain('You are a helpful assistant');
});

it('builds pi command with append system prompt', function () {
    $request = new PiRequest(prompt: 'test', appendSystemPrompt: 'Be concise');
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--append-system-prompt')
        ->and($argv)->toContain('Be concise');
});

it('builds pi command with tools list', function () {
    $request = new PiRequest(prompt: 'test', tools: ['read', 'bash', 'edit']);
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--tools')
        ->and($argv)->toContain('read,bash,edit');
});

it('builds pi command with no-tools flag', function () {
    $request = new PiRequest(prompt: 'test', noTools: true);
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--no-tools');
});

it('builds pi command with file arguments prefixed with @', function () {
    $file1 = tempnam(sys_get_temp_dir(), 'pi-test-');
    $file2 = tempnam(sys_get_temp_dir(), 'pi-test-');
    file_put_contents($file1, 'content1');
    file_put_contents($file2, 'content2');

    try {
        $request = new PiRequest(prompt: 'review', files: [$file1, $file2]);
        $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

        expect($argv)->toContain('@' . $file1)
            ->and($argv)->toContain('@' . $file2);

        // Files should come before the prompt
        $file1Pos = array_search('@' . $file1, $argv);
        $promptPos = array_search('review', $argv);
        expect($file1Pos)->toBeLessThan($promptPos);
    } finally {
        @unlink($file1);
        @unlink($file2);
    }
});

it('builds pi command with extensions', function () {
    $request = new PiRequest(prompt: 'test', extensions: ['./ext1.ts', './ext2.ts']);
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('-e')
        ->and($argv)->toContain('./ext1.ts')
        ->and($argv)->toContain('./ext2.ts');
});

it('builds pi command with no-extensions flag', function () {
    $request = new PiRequest(prompt: 'test', noExtensions: true);
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--no-extensions');
});

it('builds pi command with skills', function () {
    $request = new PiRequest(prompt: 'test', skills: ['/path/to/skill']);
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--skill')
        ->and($argv)->toContain('/path/to/skill');
});

it('builds pi command with no-skills flag', function () {
    $request = new PiRequest(prompt: 'test', noSkills: true);
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--no-skills');
});

it('builds pi command with api key', function () {
    $request = new PiRequest(prompt: 'test', apiKey: 'sk-test-123');
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--api-key')
        ->and($argv)->toContain('sk-test-123');
});

it('builds pi command with continue session', function () {
    $request = new PiRequest(prompt: 'test', continueSession: true);
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--continue');
});

it('builds pi command with specific session id', function () {
    $request = new PiRequest(prompt: 'test', sessionId: 'abc-123');
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--session')
        ->and($argv)->toContain('abc-123');
});

it('builds pi command with no-session flag', function () {
    $request = new PiRequest(prompt: 'test', noSession: true);
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--no-session');
});

it('builds pi command with session dir', function () {
    $request = new PiRequest(prompt: 'test', sessionDir: '/tmp/sessions');
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--session-dir')
        ->and($argv)->toContain('/tmp/sessions');
});

it('builds pi command with verbose flag', function () {
    $request = new PiRequest(prompt: 'test', verbose: true);
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--verbose');
});

// Validation tests

it('rejects empty prompt', function () {
    $request = new PiRequest(prompt: '   ');

    expect(fn() => (new PiCommandBuilder())->build($request))
        ->toThrow(InvalidArgumentException::class, 'Prompt must not be empty');
});

it('rejects conflicting continue and session id', function () {
    $request = new PiRequest(prompt: 'test', continueSession: true, sessionId: 'abc');

    expect(fn() => (new PiCommandBuilder())->build($request))
        ->toThrow(InvalidArgumentException::class, 'Cannot set both continueSession and sessionId');
});

it('rejects no-session with session continuation', function () {
    $request = new PiRequest(prompt: 'test', noSession: true, continueSession: true);

    expect(fn() => (new PiCommandBuilder())->build($request))
        ->toThrow(InvalidArgumentException::class, 'Cannot use noSession with session continuation');
});

it('rejects no-tools with tools list', function () {
    $request = new PiRequest(prompt: 'test', tools: ['read'], noTools: true);

    expect(fn() => (new PiCommandBuilder())->build($request))
        ->toThrow(InvalidArgumentException::class, 'Cannot set both noTools and tools list');
});

it('rejects unsupported characters in model', function () {
    $request = new PiRequest(prompt: 'test', model: 'bad model name');

    expect(fn() => (new PiCommandBuilder())->build($request))
        ->toThrow(InvalidArgumentException::class, 'model contains unsupported characters');
});

it('rejects missing file attachments', function () {
    $missingPath = sys_get_temp_dir() . '/pi-missing-file-' . uniqid('', true) . '.txt';
    $request = new PiRequest(prompt: 'test', files: [$missingPath]);

    expect(fn() => (new PiCommandBuilder())->build($request))
        ->toThrow(InvalidArgumentException::class, 'files[0] does not exist or is not a file');
});

it('accepts provider/model:thinking shorthand', function () {
    $request = new PiRequest(prompt: 'test', model: 'openai/gpt-4o');
    $argv = (new PiCommandBuilder())->build($request)->argv()->toArray();

    expect($argv)->toContain('--model')
        ->and($argv)->toContain('openai/gpt-4o');
});
