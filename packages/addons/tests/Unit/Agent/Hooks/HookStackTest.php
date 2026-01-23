<?php declare(strict_types=1);

namespace Packages\addons\tests\Unit\Agent\Hooks;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Hooks\Contracts\Hook;
use Cognesy\Addons\Agent\Hooks\Contracts\HookContext;
use Cognesy\Addons\Agent\Hooks\Data\ExecutionHookContext;
use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;
use Cognesy\Addons\Agent\Hooks\Stack\HookStack;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HookStackTest extends TestCase
{
    private function createContext(): ExecutionHookContext
    {
        return ExecutionHookContext::onStart(AgentState::empty());
    }

    #[Test]
    public function it_passes_through_to_terminal_when_empty(): void
    {
        $stack = new HookStack();
        $context = $this->createContext();

        $terminalCalled = false;
        $result = $stack->process($context, function (HookContext $ctx) use (&$terminalCalled) {
            $terminalCalled = true;
            return HookOutcome::proceed($ctx);
        });

        $this->assertTrue($terminalCalled);
        $this->assertTrue($result->isProceed());
    }

    #[Test]
    public function it_processes_single_hook(): void
    {
        $hookCalled = false;
        $hook = new class($hookCalled) implements Hook {
            public function __construct(private bool &$called) {}
            public function handle(HookContext $context, callable $next): HookOutcome {
                $this->called = true;
                return $next($context);
            }
        };

        $stack = (new HookStack())->with($hook);
        $context = $this->createContext();

        $stack->process($context, fn($ctx) => HookOutcome::proceed($ctx));

        $this->assertTrue($hookCalled);
    }

    #[Test]
    public function it_chains_hooks_in_priority_order(): void
    {
        $order = [];

        $createHook = function (string $id) use (&$order): Hook {
            return new class($order, $id) implements Hook {
                public function __construct(private array &$order, private string $id) {}
                public function handle(HookContext $context, callable $next): HookOutcome {
                    $this->order[] = "before_{$this->id}";
                    $result = $next($context);
                    $this->order[] = "after_{$this->id}";
                    return $result;
                }
            };
        };

        // Higher priority runs first (outer)
        $stack = (new HookStack())
            ->with($createHook('1'), priority: 10)
            ->with($createHook('2'), priority: 5);

        $context = $this->createContext();

        $stack->process($context, function ($ctx) use (&$order) {
            $order[] = 'terminal';
            return HookOutcome::proceed($ctx);
        });

        $this->assertEquals(
            ['before_1', 'before_2', 'terminal', 'after_2', 'after_1'],
            $order,
        );
    }

    #[Test]
    public function it_can_block_execution(): void
    {
        $blockHook = new class implements Hook {
            public function handle(HookContext $context, callable $next): HookOutcome {
                return HookOutcome::block('Blocked by test');
            }
        };

        $stack = (new HookStack())->with($blockHook);
        $context = $this->createContext();

        $terminalCalled = false;
        $result = $stack->process($context, function ($ctx) use (&$terminalCalled) {
            $terminalCalled = true;
            return HookOutcome::proceed($ctx);
        });

        $this->assertFalse($terminalCalled);
        $this->assertTrue($result->isBlocked());
        $this->assertEquals('Blocked by test', $result->reason());
    }

    #[Test]
    public function it_can_stop_execution(): void
    {
        $stopHook = new class implements Hook {
            public function handle(HookContext $context, callable $next): HookOutcome {
                return HookOutcome::stop('Stopped by test');
            }
        };

        $stack = (new HookStack())->with($stopHook);
        $context = $this->createContext();

        $result = $stack->process($context, fn($ctx) => HookOutcome::proceed($ctx));

        $this->assertTrue($result->isStopped());
        $this->assertEquals('Stopped by test', $result->reason());
    }

    #[Test]
    public function it_propagates_modified_context(): void
    {
        $modifyHook = new class implements Hook {
            public function handle(HookContext $context, callable $next): HookOutcome {
                $modified = $context->withMetadata('modified', true);
                return $next($modified);
            }
        };

        $stack = (new HookStack())->with($modifyHook);
        $context = $this->createContext();

        $receivedMetadata = null;
        $stack->process($context, function (HookContext $ctx) use (&$receivedMetadata) {
            $receivedMetadata = $ctx->metadata();
            return HookOutcome::proceed($ctx);
        });

        $this->assertArrayHasKey('modified', $receivedMetadata);
        $this->assertTrue($receivedMetadata['modified']);
    }

    #[Test]
    public function it_maintains_registration_order_for_equal_priority(): void
    {
        $order = [];

        $createHook = function (string $id) use (&$order): Hook {
            return new class($order, $id) implements Hook {
                public function __construct(private array &$order, private string $id) {}
                public function handle(HookContext $context, callable $next): HookOutcome {
                    $this->order[] = $this->id;
                    return $next($context);
                }
            };
        };

        // All same priority, should maintain registration order
        $stack = (new HookStack())
            ->with($createHook('A'), priority: 0)
            ->with($createHook('B'), priority: 0)
            ->with($createHook('C'), priority: 0);

        $context = $this->createContext();
        $stack->process($context, fn($ctx) => HookOutcome::proceed($ctx));

        $this->assertEquals(['A', 'B', 'C'], $order);
    }

    #[Test]
    public function it_reports_empty_status(): void
    {
        $stack = new HookStack();
        $this->assertTrue($stack->isEmpty());
        $this->assertEquals(0, $stack->count());

        $hook = new class implements Hook {
            public function handle(HookContext $context, callable $next): HookOutcome {
                return $next($context);
            }
        };

        $stack = $stack->with($hook);
        $this->assertFalse($stack->isEmpty());
        $this->assertEquals(1, $stack->count());
    }

    #[Test]
    public function it_adds_multiple_hooks_at_once(): void
    {
        $order = [];

        $createHook = function (string $id) use (&$order): Hook {
            return new class($order, $id) implements Hook {
                public function __construct(private array &$order, private string $id) {}
                public function handle(HookContext $context, callable $next): HookOutcome {
                    $this->order[] = $this->id;
                    return $next($context);
                }
            };
        };

        $stack = (new HookStack())->withAll([
            $createHook('A'),
            ['hook' => $createHook('B'), 'priority' => 10],
            $createHook('C'),
        ]);

        $context = $this->createContext();
        $stack->process($context, fn($ctx) => HookOutcome::proceed($ctx));

        // B has priority 10, so it runs first, then A and C in registration order
        $this->assertEquals(['B', 'A', 'C'], $order);
    }
}
