<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Tools;

use Cognesy\Agents\AgentBuilder\Capabilities\Bash\BashPolicy;
use Cognesy\Agents\AgentBuilder\Capabilities\Bash\BashTool;
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Testing\MockSandbox;

describe('BashTool', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/bash_tool_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }
    });

    it('has correct name and description', function () {
        $tool = new BashTool(baseDir: $this->tempDir);

        expect($tool->name())->toBe('bash');
        expect($tool->description())->toContain('Execute a bash command');
    });

    it('executes simple echo command', function () {
        $tool = new BashTool(baseDir: $this->tempDir);

        $result = $tool('echo "Hello World"');

        expect($result)->toContain('Hello World');
    });

    it('executes command and returns stdout', function () {
        $tool = new BashTool(baseDir: $this->tempDir);

        $result = $tool('pwd');

        expect($result)->toContain($this->tempDir);
    });

    it('returns exit code for failed commands', function () {
        $tool = new BashTool(baseDir: $this->tempDir);

        $result = $tool('exit 1');

        expect($result)->toContain('Exit code: 1');
    });

    it('captures stderr output', function () {
        $tool = new BashTool(baseDir: $this->tempDir);

        $result = $tool('echo "error message" >&2');

        expect($result)->toContain('STDERR:');
        expect($result)->toContain('error message');
    });

    it('returns no output message for silent commands', function () {
        $tool = new BashTool(baseDir: $this->tempDir);

        $result = $tool('true');

        expect($result)->toBe('(no output)');
    });

    it('creates tool from directory', function () {
        $tool = BashTool::inDirectory($this->tempDir);

        expect($tool)->toBeInstanceOf(BashTool::class);
        expect($tool('pwd'))->toContain($this->tempDir);
    });

    it('creates tool with custom policy', function () {
        $policy = ExecutionPolicy::in($this->tempDir)
            ->withTimeout(60)
            ->inheritEnvironment();

        $tool = BashTool::withPolicy($policy);

        expect($tool)->toBeInstanceOf(BashTool::class);
    });

    it('executes command with pipes', function () {
        $tool = new BashTool(baseDir: $this->tempDir);

        $result = $tool('echo -e "line1\nline2\nline3" | wc -l');

        expect($result)->toContain('3');
    });

    it('handles command with environment variables', function () {
        $tool = new BashTool(baseDir: $this->tempDir);

        $result = $tool('TEST_VAR=hello && echo $TEST_VAR');

        expect($result)->toContain('hello');
    });

    it('truncates output with head/tail policy', function () {
        $policy = new BashPolicy(maxOutputChars: 10, headChars: 4, tailChars: 6);
        $tool = new BashTool(baseDir: $this->tempDir, outputPolicy: $policy);

        $result = $tool("printf '0123456789ABCDEFGHIJ'");

        expect($result)->toContain('...(truncated)...');
        expect($result)->toContain("0123\n...\nEFGHIJ");
        expect($result)->not->toContain('4567');
    });

    it('generates valid tool schema', function () {
        $tool = new BashTool(baseDir: $this->tempDir);
        $schema = $tool->toToolSchema();

        expect($schema)->toMatchArray([
            'type' => 'function',
            'function' => [
                'name' => 'bash',
                'description' => $tool->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'command' => [
                            'type' => 'string',
                            'description' => 'The bash command to execute',
                        ],
                    ],
                    'required' => ['command'],
                    'additionalProperties' => false,
                ],
            ],
        ]);
    });

    it('executes using MockSandbox', function () {
        $sandbox = new MockSandbox(
            policy: ExecutionPolicy::in($this->tempDir),
            responses: [
                'bash -c echo "Hello"' => [
                    ['stdout' => 'Hello', 'exit_code' => 0],
                ],
            ],
        );

        $tool = BashTool::withSandbox($sandbox);

        $result = $tool('echo "Hello"');

        expect($result)->toContain('Hello');
        expect($sandbox->commands())->toBe([
            ['bash', '-c', 'echo "Hello"'],
        ]);
    });
});
