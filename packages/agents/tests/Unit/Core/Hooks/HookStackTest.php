<?php declare(strict_types=1);

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;
use Cognesy\Agents\Hooks\HookStack;
use Cognesy\Agents\Hooks\HookTrigger;
use Cognesy\Agents\Hooks\HookTriggers;
use Cognesy\Agents\Hooks\RegisteredHooks;

it('accumulates hooks when chaining withHook()', function () {
    $stack = new HookStack(new RegisteredHooks());

    $calls = [];

    $hookA = new class($calls) implements HookInterface {
        private array $callsRef;

        public function __construct(array &$callsRef) {
            $this->callsRef = &$callsRef;
        }

        public function handle(HookContext $context): HookContext {
            $this->callsRef[] = 'a';
            return $context;
        }
    };

    $hookB = new class($calls) implements HookInterface {
        private array $callsRef;

        public function __construct(array &$callsRef) {
            $this->callsRef = &$callsRef;
        }

        public function handle(HookContext $context): HookContext {
            $this->callsRef[] = 'b';
            return $context;
        }
    };

    $stack = $stack
        ->with($hookA, HookTriggers::with(HookTrigger::BeforeExecution))
        ->with($hookB, HookTriggers::with(HookTrigger::BeforeExecution));

    $stack->intercept(HookContext::beforeExecution(
        state: AgentState::empty(),
    ));

    expect($calls)->toBe(['a', 'b']);
});

it('orders hooks by descending priority', function () {
    $stack = new HookStack(new RegisteredHooks());

    $calls = [];

    $hookLow = new class($calls) implements HookInterface {
        private array $callsRef;

        public function __construct(array &$callsRef) {
            $this->callsRef = &$callsRef;
        }

        public function handle(HookContext $context): HookContext {
            $this->callsRef[] = 'low';
            return $context;
        }
    };

    $hookHigh = new class($calls) implements HookInterface {
        private array $callsRef;

        public function __construct(array &$callsRef) {
            $this->callsRef = &$callsRef;
        }

        public function handle(HookContext $context): HookContext {
            $this->callsRef[] = 'high';
            return $context;
        }
    };

    $stack = $stack
        ->with($hookLow, HookTriggers::with(HookTrigger::BeforeExecution), priority: 0)
        ->with($hookHigh, HookTriggers::with(HookTrigger::BeforeExecution), priority: 10);

    $stack->intercept(HookContext::beforeExecution(
        state: AgentState::empty(),
    ));

    expect($calls)->toBe(['high', 'low']);
});
