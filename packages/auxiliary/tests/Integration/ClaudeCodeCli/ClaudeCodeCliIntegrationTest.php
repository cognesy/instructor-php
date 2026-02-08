<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\ClaudeCode\Application\Builder\ClaudeCommandBuilder;
use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\AgentCtrl\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\AgentCtrl\Common\Contract\CommandExecutor;
use Cognesy\AgentCtrl\Common\Execution\ExecutionPolicy;
use Cognesy\Sandbox\Data\ExecResult;

it('builds, executes stub, and parses structured output', function () {
    $request = new ClaudeRequest(
        prompt: 'demo',
        outputFormat: OutputFormat::Json,
        permissionMode: PermissionMode::Plan,
        maxTurns: 2,
        verbose: true,
    );

    $spec = (new ClaudeCommandBuilder())->buildHeadless($request);

    $executor = new class implements CommandExecutor {
        public array $capturedArgv = [];

        public function execute(\Cognesy\AgentCtrl\Common\Value\CommandSpec $command): ExecResult {
            $this->capturedArgv = $command->argv()->toArray();
            $payload = '{"type":"result","result":"ok","session_id":"abc","total_cost_usd":0.001}';
            return new ExecResult($payload, '', 0, 0.01);
        }

        public function policy(): ExecutionPolicy {
            return ExecutionPolicy::default();
        }
    };

    $result = $executor->execute($spec);
    $response = (new ResponseParser())->parse($result, OutputFormat::Json);

    expect($executor->capturedArgv)->toContain('claude', '-p', 'demo');
    expect($response->decoded()->count())->toBe(1);
    expect($response->decoded()->all()[0]->data()['result'])->toBe('ok');
});
