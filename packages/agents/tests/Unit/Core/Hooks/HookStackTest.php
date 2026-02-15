<?php declare(strict_types=1);

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Hook\HookStack;

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

it('invokes onStop hooks when stop context is intercepted', function () {
    $stack = new HookStack(new RegisteredHooks());

    $calls = [];

    $hook = new class($calls) implements HookInterface {
        private array $callsRef;

        public function __construct(array &$callsRef) {
            $this->callsRef = &$callsRef;
        }

        public function handle(HookContext $context): HookContext {
            $this->callsRef[] = $context->triggerType()->value;
            return $context;
        }
    };

    $stack = $stack->with($hook, HookTriggers::onStop());

    $stack->intercept(HookContext::onStop(
        state: AgentState::empty(),
    ));

    expect($calls)->toBe(['on_stop']);
});
