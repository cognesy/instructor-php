<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Exceptions\ToolExecutionException;
use Cognesy\Agents\Hooks\Interceptors\PassThroughInterceptor;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use RuntimeException;

class FailingTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(name: 'failing_tool', description: 'A tool that always fails');
    }

    #[\Override]
    public function __invoke(mixed ...$args): mixed
    {
        throw new RuntimeException('Tool execution failed');
    }
}

class SucceedingTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(name: 'succeeding_tool', description: 'A tool that always succeeds');
    }

    #[\Override]
    public function __invoke(mixed ...$args): mixed
    {
        return 'success';
    }
}

it('throws exception on tool failure when throwOnToolFailure is true', function () {
    $tools = new Tools(new FailingTool());
    $executor = new ToolExecutor(
        tools: $tools,
        eventEmitter: new AgentEventEmitter(),
        interceptor: new PassThroughInterceptor(),
        throwOnToolFailure: true,
    );

    $toolCalls = new ToolCalls(
        new ToolCall(id: 'call-1', name: 'failing_tool', args: []),
    );

    expect(fn() => $executor->executeTools($toolCalls, AgentState::empty()))
        ->toThrow(ToolExecutionException::class);
});

it('does not throw on tool failure when throwOnToolFailure is false', function () {
    $tools = new Tools(new FailingTool());
    $executor = new ToolExecutor(
        tools: $tools,
        eventEmitter: new AgentEventEmitter(),
        interceptor: new PassThroughInterceptor(),
        throwOnToolFailure: false,
    );

    $toolCalls = new ToolCalls(
        new ToolCall(id: 'call-1', name: 'failing_tool', args: []),
    );

    $result = $executor->executeTools($toolCalls, AgentState::empty());

    expect($result->hasExecutions())->toBeTrue()
        ->and($result->first()->hasError())->toBeTrue();
});

it('executes successful tools without throwing', function () {
    $tools = new Tools(new SucceedingTool());
    $executor = new ToolExecutor(
        tools: $tools,
        eventEmitter: new AgentEventEmitter(),
        interceptor: new PassThroughInterceptor(),
        throwOnToolFailure: true,
    );

    $toolCalls = new ToolCalls(
        new ToolCall(id: 'call-1', name: 'succeeding_tool', args: []),
    );

    $result = $executor->executeTools($toolCalls, AgentState::empty());

    expect($result->hasExecutions())->toBeTrue()
        ->and($result->first()->hasError())->toBeFalse()
        ->and($result->first()->value())->toBe('success');
});
