<?php declare(strict_types=1);

namespace Packages\addons\tests\Unit\Agent\Hooks;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;
use Cognesy\Addons\Agent\Hooks\Data\ToolHookContext;
use Cognesy\Addons\Agent\Hooks\Hooks\BeforeToolHook;
use Cognesy\Addons\Agent\Hooks\Matchers\ToolNameMatcher;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BeforeToolHookTest extends TestCase
{
    private function createToolContext(string $name = 'test_tool', array $args = []): ToolHookContext
    {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_123',
            'name' => $name,
            'arguments' => $args,
        ]);

        return ToolHookContext::beforeTool($toolCall, AgentState::empty());
    }

    #[Test]
    public function it_allows_tool_call_when_returning_proceed(): void
    {
        $hook = new BeforeToolHook(
            callback: fn(ToolHookContext $ctx) => HookOutcome::proceed(),
        );

        $context = $this->createToolContext();
        $nextCalled = false;

        $result = $hook->handle($context, function ($ctx) use (&$nextCalled) {
            $nextCalled = true;
            return HookOutcome::proceed($ctx);
        });

        $this->assertTrue($nextCalled);
        $this->assertTrue($result->isProceed());
    }

    #[Test]
    public function it_blocks_tool_call_when_returning_null(): void
    {
        $hook = new BeforeToolHook(
            callback: fn(ToolHookContext $ctx) => null,
        );

        $context = $this->createToolContext();
        $nextCalled = false;

        $result = $hook->handle($context, function ($ctx) use (&$nextCalled) {
            $nextCalled = true;
            return HookOutcome::proceed($ctx);
        });

        $this->assertFalse($nextCalled);
        $this->assertTrue($result->isBlocked());
    }

    #[Test]
    public function it_blocks_tool_call_when_returning_block_outcome(): void
    {
        $hook = new BeforeToolHook(
            callback: fn(ToolHookContext $ctx) => HookOutcome::block('Dangerous command'),
        );

        $context = $this->createToolContext();
        $nextCalled = false;

        $result = $hook->handle($context, function ($ctx) use (&$nextCalled) {
            $nextCalled = true;
            return HookOutcome::proceed($ctx);
        });

        $this->assertFalse($nextCalled);
        $this->assertTrue($result->isBlocked());
        $this->assertEquals('Dangerous command', $result->reason());
    }

    #[Test]
    public function it_modifies_tool_call_when_returning_tool_call(): void
    {
        $hook = new BeforeToolHook(
            callback: function (ToolHookContext $ctx): ToolCall {
                $args = $ctx->toolCall()->args();
                $args['timeout'] = 30;
                return $ctx->toolCall()->withArgs($args);
            },
        );

        $context = $this->createToolContext('test', ['query' => 'test']);
        $receivedArgs = null;

        $hook->handle($context, function (ToolHookContext $ctx) use (&$receivedArgs) {
            $receivedArgs = $ctx->toolCall()->args();
            return HookOutcome::proceed($ctx);
        });

        $this->assertEquals(['query' => 'test', 'timeout' => 30], $receivedArgs);
    }

    #[Test]
    public function it_skips_when_matcher_does_not_match(): void
    {
        $callbackCalled = false;
        $hook = new BeforeToolHook(
            callback: function (ToolHookContext $ctx) use (&$callbackCalled) {
                $callbackCalled = true;
                return HookOutcome::proceed();
            },
            matcher: new ToolNameMatcher('bash'),
        );

        // Different tool name
        $context = $this->createToolContext('read_file');
        $nextCalled = false;

        $result = $hook->handle($context, function ($ctx) use (&$nextCalled) {
            $nextCalled = true;
            return HookOutcome::proceed($ctx);
        });

        $this->assertFalse($callbackCalled);
        $this->assertTrue($nextCalled);
        $this->assertTrue($result->isProceed());
    }

    #[Test]
    public function it_runs_when_matcher_matches(): void
    {
        $callbackCalled = false;
        $hook = new BeforeToolHook(
            callback: function (ToolHookContext $ctx) use (&$callbackCalled) {
                $callbackCalled = true;
                return HookOutcome::proceed();
            },
            matcher: new ToolNameMatcher('bash'),
        );

        $context = $this->createToolContext('bash');

        $hook->handle($context, fn($ctx) => HookOutcome::proceed($ctx));

        $this->assertTrue($callbackCalled);
    }

    #[Test]
    public function it_stops_execution_when_returning_stop_outcome(): void
    {
        $hook = new BeforeToolHook(
            callback: fn(ToolHookContext $ctx) => HookOutcome::stop('Budget exceeded'),
        );

        $context = $this->createToolContext();
        $nextCalled = false;

        $result = $hook->handle($context, function ($ctx) use (&$nextCalled) {
            $nextCalled = true;
            return HookOutcome::proceed($ctx);
        });

        $this->assertFalse($nextCalled);
        $this->assertTrue($result->isStopped());
        $this->assertEquals('Budget exceeded', $result->reason());
    }
}
