<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Builder\Collections\DeferredToolProviders;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Context\CanAcceptMessageCompiler;
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Interception\PassThroughInterceptor;
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

/** @internal */
final readonly class AgentConfigurator implements CanConfigureAgent
{
    private function __construct(
        private Tools $tools,
        private CanCompileMessages $contextCompiler,
        private CanUseTools $toolUseDriver,
        private HookStack $hooks,
        private DeferredToolProviders $deferredTools,
        private CanHandleEvents $events,
    ) {}

    public static function base(?CanHandleEvents $parentEvents = null): self {
        $events = new EventDispatcher('agent-builder', $parentEvents);
        $llm = LLMProvider::new();

        return new self(
            tools: new Tools(),
            contextCompiler: new ConversationWithCurrentToolTrace(),
            toolUseDriver: new ToolCallingDriver(
                llm: $llm,
                events: $events,
                inference: InferenceRuntime::fromProvider(
                    provider: $llm,
                    events: $events,
                ),
            ),
            hooks: new HookStack(new RegisteredHooks()),
            deferredTools: DeferredToolProviders::empty(),
            events: $events,
        );
    }

    /** @param CanProvideAgentCapability<self> $capability */
    public function install(CanProvideAgentCapability $capability): self {
        /** @var self $configured */
        $configured = $capability->configure($this);
        return $configured;
    }

    public function toAgentLoop(): AgentLoop {
        $driver = $this->resolveDriver();
        $tools = $this->resolveTools($driver);
        $interceptor = $this->resolveInterceptor();

        $executor = new ToolExecutor(
            tools: $tools,
            events: $this->events,
            interceptor: $interceptor,
            throwOnToolFailure: false,
        );

        return new AgentLoop(
            tools: $tools,
            toolExecutor: $executor,
            driver: $driver,
            events: $this->events,
            interceptor: $interceptor,
        );
    }

    // TOOLS ////////////////////////////////////////////////////////

    #[\Override]
    public function tools(): Tools {
        return $this->tools;
    }

    #[\Override]
    public function withTools(Tools $tools): self {
        return $this->with(tools: $tools);
    }

    // CONTEXT COMPILER ////////////////////////////////////////////////////////

    #[\Override]
    public function contextCompiler(): CanCompileMessages {
        return $this->contextCompiler;
    }

    #[\Override]
    public function withContextCompiler(CanCompileMessages $compiler): self {
        return $this->with(contextCompiler: $compiler);
    }

    // TOOL USE DRIVER ////////////////////////////////////////////////////////

    #[\Override]
    public function toolUseDriver(): CanUseTools {
        return $this->toolUseDriver;
    }

    #[\Override]
    public function withToolUseDriver(CanUseTools $driver): self {
        return $this->with(toolUseDriver: $driver);
    }

    // HOOKS ////////////////////////////////////////////////////////

    #[\Override]
    public function hooks(): HookStack {
        return $this->hooks;
    }

    #[\Override]
    public function withHooks(HookStack $hooks): self {
        return $this->with(hooks: $hooks);
    }

    // DEFERRED TOOLS ////////////////////////////////////////////////////////

    #[\Override]
    public function deferredTools(): DeferredToolProviders {
        return $this->deferredTools;
    }

    #[\Override]
    public function withDeferredTools(DeferredToolProviders $deferredTools): self {
        return $this->with(deferredTools: $deferredTools);
    }

    // EVENTS ////////////////////////////////////////////////////////

    #[\Override]
    public function events(): CanHandleEvents {
        return $this->events;
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    private function with(
        ?Tools $tools = null,
        ?CanCompileMessages $contextCompiler = null,
        ?CanUseTools $toolUseDriver = null,
        ?HookStack $hooks = null,
        ?DeferredToolProviders $deferredTools = null,
        ?CanHandleEvents $events = null,
    ) : self {
        return new self(
            tools: $tools ?? $this->tools,
            contextCompiler: $contextCompiler ?? $this->contextCompiler,
            toolUseDriver: $toolUseDriver ?? $this->toolUseDriver,
            hooks: $hooks ?? $this->hooks,
            deferredTools: $deferredTools ?? $this->deferredTools,
            events: $events ?? $this->events,
        );
    }

    private function resolveDriver(): CanUseTools {
        return $this->normalizeDriver($this->toolUseDriver, $this->contextCompiler);
    }

    private function normalizeDriver(CanUseTools $driver, CanCompileMessages $compiler): CanUseTools {
        return match (true) {
            $driver instanceof CanAcceptMessageCompiler => $driver->withMessageCompiler($compiler),
            default => $driver,
        };
    }

    private function resolveTools(CanUseTools $driver): Tools {
        return $this->deferredTools->resolve($this->tools, $driver, $this->events);
    }

    private function resolveInterceptor(): HookStack|PassThroughInterceptor {
        $hooks = $this->hooks->hooks();
        if ($hooks === []) {
            return new PassThroughInterceptor();
        }

        $stack = new HookStack(new RegisteredHooks(), $this->events);
        foreach ($hooks as $hook) {
            $stack = $stack->withHook($hook);
        }
        return $stack;
    }
}
