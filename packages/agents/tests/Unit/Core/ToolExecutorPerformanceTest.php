<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Hooks\Interceptors\PassThroughInterceptor;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;

final class NoOpTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(name: 'noop', description: 'Does nothing');
    }

    #[\Override]
    public function __invoke(mixed ...$args): mixed
    {
        return 'ok';
    }
}

it('executes many tool calls efficiently without O(n²) degradation', function () {
    $tools = new Tools(new NoOpTool());
    $executor = new ToolExecutor(
        tools: $tools,
        eventEmitter: new AgentEventEmitter(),
        interceptor: new PassThroughInterceptor(),
        throwOnToolFailure: false,
    );

    $callCount = 500;
    $toolCallsArray = [];
    for ($i = 0; $i < $callCount; $i++) {
        $toolCallsArray[] = new ToolCall(id: "call-{$i}", name: 'noop', args: []);
    }
    $toolCalls = new ToolCalls(...$toolCallsArray);

    $startTime = microtime(true);
    $result = $executor->executeTools($toolCalls, AgentState::empty());
    $elapsed = microtime(true) - $startTime;

    expect($result->hasExecutions())->toBeTrue()
        ->and(count($result->all()))->toBe($callCount)
        ->and($elapsed)->toBeLessThan(1.0); // Should complete well under 1 second
});
