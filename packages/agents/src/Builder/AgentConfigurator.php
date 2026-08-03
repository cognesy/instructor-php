<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Builder\Collections\DeferredToolProviders;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Builder\Data\ResolvedTools;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Context\CanAcceptMessageCompiler;
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
use Cognesy\Agents\Drivers\CanAcceptToolRuntime;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Interception\CanAcceptLifecycleInterceptor;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;
use Cognesy\Agents\Interception\PassThroughInterceptor;
use Cognesy\Agents\Profile\AgentIdentity;
use Cognesy\Agents\Profile\AgentProfile;
use Cognesy\Agents\Profile\AgentProfileBinder;
use Cognesy\Agents\Profile\AgentProfileContributors;
use Cognesy\Agents\Profile\CapabilityProfile;
use Cognesy\Agents\Profile\CapabilityProfileList;
use Cognesy\Agents\Profile\Contracts\CanContributeToAgentProfile;
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Logging\EventLog;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Metadata;
use Override;

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
        private AgentIdentity $identity,
        private CapabilityProfileList $installedCapabilities,
        private AgentProfileContributors $profileContributors,
        private Metadata $profileMetadata,
    ) {}

    public static function base(
        ?CanHandleEvents $parentEvents = null,
        ?AgentIdentity $identity = null,
    ): self {
        $events = $parentEvents !== null
            ? new EventDispatcher('agent-builder', $parentEvents)
            : EventLog::root('agent-builder');
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
            identity: $identity ?? AgentIdentity::anonymous(),
            installedCapabilities: CapabilityProfileList::empty(),
            profileContributors: AgentProfileContributors::empty(),
            profileMetadata: Metadata::empty(),
        );
    }

    /** @param CanProvideAgentCapability<self> $capability */
    public function install(CanProvideAgentCapability $capability): self {
        /** @var self $configured */
        $configured = $capability->configure($this);
        $contributors = match (true) {
            $capability instanceof CanContributeToAgentProfile
                => $configured->profileContributors->with($capability),
            default => $configured->profileContributors,
        };
        return $configured->with(
            installedCapabilities: $configured->installedCapabilities->with(
                new CapabilityProfile($capability::capabilityName(), $capability::class),
            ),
            profileContributors: $contributors,
        );
    }

    public function toAgentLoop(): AgentLoop {
        $driver = $this->normalizeDriver($this->toolUseDriver, $this->contextCompiler);
        $resolvedTools = $this->resolveTools($driver);
        $tools = $resolvedTools->tools();
        $interceptor = $this->resolveInterceptor();
        $profile = AgentProfile::fromResolved(
            identity: $this->identity,
            tools: $tools,
            capabilities: $this->installedCapabilities,
            driver: $driver,
            interceptor: $interceptor,
            contributors: $this->profileContributors,
            metadata: $this->profileMetadata,
            deferredToolNames: $resolvedTools->deferredNames(),
        );
        $tools = AgentProfileBinder::tools($tools, $profile);
        $driver = AgentProfileBinder::driver($driver, $profile);
        $interceptor = AgentProfileBinder::interceptor($interceptor, $profile);

        $executor = new ToolExecutor(
            tools: $tools,
            events: $this->events,
            interceptor: $interceptor,
            throwOnToolFailure: false,
        );
        $driver = $this->bindToolRuntime($driver, $tools, $executor);
        $driver = $this->bindInterceptor($driver, $interceptor);

        return new AgentLoop(
            tools: $tools,
            toolExecutor: $executor,
            driver: $driver,
            events: $this->events,
            interceptor: $interceptor,
            profile: $profile,
        );
    }

    // TOOLS ////////////////////////////////////////////////////////

    #[Override]
    public function tools(): Tools {
        return $this->tools;
    }

    #[Override]
    public function withTools(Tools $tools): self {
        return $this->with(tools: $tools);
    }

    // CONTEXT COMPILER ////////////////////////////////////////////////////////

    #[Override]
    public function contextCompiler(): CanCompileMessages {
        return $this->contextCompiler;
    }

    #[Override]
    public function withContextCompiler(CanCompileMessages $compiler): self {
        return $this->with(contextCompiler: $compiler);
    }

    // TOOL USE DRIVER ////////////////////////////////////////////////////////

    #[Override]
    public function toolUseDriver(): CanUseTools {
        return $this->toolUseDriver;
    }

    #[Override]
    public function withToolUseDriver(CanUseTools $driver): self {
        return $this->with(toolUseDriver: $driver);
    }

    // HOOKS ////////////////////////////////////////////////////////

    #[Override]
    public function hooks(): HookStack {
        return $this->hooks;
    }

    #[Override]
    public function withHooks(HookStack $hooks): self {
        return $this->with(hooks: $hooks);
    }

    // DEFERRED TOOLS ////////////////////////////////////////////////////////

    #[Override]
    public function deferredTools(): DeferredToolProviders {
        return $this->deferredTools;
    }

    #[Override]
    public function withDeferredTools(DeferredToolProviders $deferredTools): self {
        return $this->with(deferredTools: $deferredTools);
    }

    // EVENTS ////////////////////////////////////////////////////////

    #[Override]
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
        ?AgentIdentity $identity = null,
        ?CapabilityProfileList $installedCapabilities = null,
        ?AgentProfileContributors $profileContributors = null,
        ?Metadata $profileMetadata = null,
    ): self {
        return new self(
            tools: $tools ?? $this->tools,
            contextCompiler: $contextCompiler ?? $this->contextCompiler,
            toolUseDriver: $toolUseDriver ?? $this->toolUseDriver,
            hooks: $hooks ?? $this->hooks,
            deferredTools: $deferredTools ?? $this->deferredTools,
            events: $events ?? $this->events,
            identity: $identity ?? $this->identity,
            installedCapabilities: $installedCapabilities ?? $this->installedCapabilities,
            profileContributors: $profileContributors ?? $this->profileContributors,
            profileMetadata: $profileMetadata ?? $this->profileMetadata,
        );
    }

    private function normalizeDriver(CanUseTools $driver, CanCompileMessages $compiler): CanUseTools {
        return match (true) {
            $driver instanceof CanAcceptMessageCompiler => $driver->withMessageCompiler($compiler),
            default => $driver,
        };
    }

    private function bindToolRuntime(
        CanUseTools $driver,
        Tools $tools,
        ToolExecutor $executor,
    ): CanUseTools {
        return match (true) {
            $driver instanceof CanAcceptToolRuntime => $driver->withToolRuntime($tools, $executor),
            default => $driver,
        };
    }

    private function bindInterceptor(
        CanUseTools $driver,
        CanInterceptAgentLifecycle $interceptor,
    ): CanUseTools {
        return match (true) {
            $driver instanceof CanAcceptLifecycleInterceptor => $driver->withInterceptor($interceptor),
            default => $driver,
        };
    }

    private function resolveTools(CanUseTools $driver): ResolvedTools {
        return $this->deferredTools->resolveWithProvenance($this->tools, $driver, $this->events);
    }

    private function resolveInterceptor(): HookStack|PassThroughInterceptor {
        $hooks = $this->hooks->hooks();
        if ($hooks === []) {
            return new PassThroughInterceptor();
        }

        return $this->hooks->withEventHandler($this->events);
    }
}
