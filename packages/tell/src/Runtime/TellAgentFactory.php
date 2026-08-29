<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Closure;
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Bash\BashPolicy;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Capability\Cancellation\CooperativeCancellationHook;
use Cognesy\Agents\Capability\Definitions\UseAgentDefinitions;
use Cognesy\Agents\Capability\Describe\UseSelfDescription;
use Cognesy\Agents\Capability\Prompt\UseSystemPrompt;
use Cognesy\Agents\Capability\SelfKnowledge\UseSelfKnowledge;
use Cognesy\Agents\Capability\Subagent\SubagentPolicy;
use Cognesy\Agents\Capability\Subagent\UseSubagents;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Discovery\CapabilityDiscovery;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\FileSessionStore;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\AgentDefinitionValidator;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Config\Dsn;
use Cognesy\Config\EnvTemplate;
use Cognesy\Config\Secrets\DotenvFileSecretSource;
use Cognesy\Config\Secrets\EnvironmentSecretSource;
use Cognesy\Config\Secrets\SecretResolver;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\Capability\AskUser\TellAskUserCapability;
use Cognesy\Tell\Capability\Coding\TellCodingTools;
use Cognesy\Tell\Contracts\CanResolveTellModel;
use Cognesy\Tell\Diagnostics\StartupScanCounter;
use Cognesy\Tell\Diagnostics\TellDiagnostics;
use Cognesy\Tell\Observability\ExecutionTraceWriter;
use Cognesy\Tell\TellRequest;
use Cognesy\Tell\Workspace\WorkspaceManager;
use RuntimeException;

final readonly class TellAgentFactory
{
    /** @var Closure(AgentLoop): AgentLoop|null */
    private ?Closure $decorateLoop;

    private CanReadTellClock $clock;

    /** @param callable(AgentLoop): AgentLoop|null $decorateLoop */
    public function __construct(
        private TellPaths $paths,
        ?callable $decorateLoop = null,
        ?CanReadTellClock $clock = null,
        private ?CanUseTools $driver = null,
        private ?StartupScanCounter $startupScans = null,
        private ?string $composerVendorDir = null,
        private ?string $rootComposerPath = null,
        private ?CanResolveTellModel $modelResolver = null,
    ) {
        $this->decorateLoop = match ($decorateLoop) {
            null => null,
            default => Closure::fromCallable($decorateLoop),
        };
        $this->clock = $clock ?? new SystemTellClock;
    }

    public static function installed(): self
    {
        return new self(TellPaths::installed());
    }

    public function definitions(string $projectPath): AgentDefinitionRegistry
    {
        $this->startupScans?->recordAgentDefinitionScan();
        $registry = new AgentDefinitionRegistry;
        $registry->autoDiscover(
            projectPath: $projectPath,
            packagePath: $this->paths->packageAgents,
            userPath: $this->paths->userAgents,
        );

        return $registry;
    }

    public function definition(TellOptions $options): AgentDefinition
    {
        $definition = $this->definitions($options->directory)->get($options->agent);

        return $this->configureDefinition($definition, $options);
    }

    public function configureDefinition(AgentDefinition $definition, TellOptions $options): AgentDefinition
    {
        if ($this->driver !== null) {
            $this->assertReasoningSupportedWithoutCredentials($options);
        }
        $llmConfig = match ($this->driver) {
            null => $this->llmConfig($options),
            default => null,
        };
        if ($llmConfig !== null && $this->modelResolver === null && $options->dsn === '') {
            $this->assertCredentialAvailable($llmConfig, $options->connection);
        }

        return new AgentDefinition(
            name: $definition->name,
            description: $definition->description,
            systemPrompt: $definition->systemPrompt,
            label: $definition->label,
            llmConfig: $llmConfig,
            capabilities: $definition->capabilities,
            tools: $definition->tools,
            toolsDeny: $definition->toolsDeny,
            skills: $definition->skills,
            budget: new ExecutionBudget(maxSteps: $options->maxSteps),
            metadata: $definition->metadata,
        );
    }

    public function build(
        TellOptions $options,
        ?AgentDefinition $definition = null,
        ?CanProvideCancellationSignal $cancellation = null,
        ?TellDelegationScope $delegation = null,
        ?TellDiagnostics $diagnostics = null,
    ): AgentLoop {
        // A supplied definition has already been resolved for this immutable
        // request. Re-resolving here made credentials/model selection depend on
        // timing and performed duplicate filesystem/environment reads.
        $definition ??= $this->definition($options);
        $definitions = $this->definitions($options->directory);
        $capabilities = new AgentCapabilityRegistry;
        $tools = new ToolRegistry;
        $this->startupScans?->recordComposerManifestScan();
        $discovery = CapabilityDiscovery::discover(
            $capabilities,
            $tools,
            $this->composerVendorDir,
            $this->rootComposerPath,
        );
        $diagnostics?->recordExtensionDiscovery($discovery);

        $policy = $options->policy ?? TellExecutionPolicy::defaults();
        $capabilities->register('tell.coding', new TellCodingTools(
            $options->directory,
            $this->bashPolicy($policy),
        ));
        $capabilities->register('tell.ask_user', new TellAskUserCapability($options->answers));
        $capabilities->register('use_subagents', new UseSubagents(
            provider: $definitions,
            policy: new SubagentPolicy(maxDepth: 1),
            executor: $delegation === null ? null : new TellSubagentExecutor($this, $options, $delegation, $diagnostics),
            currentDepth: $delegation === null ? 0 : $delegation->depth,
        ));
        $capabilities->register('tell.system_prompt', new UseSystemPrompt);
        $capabilities->register('tell.self_knowledge', new UseSelfKnowledge);
        $capabilities->register('tell.self_description', new UseSelfDescription);
        $capabilities->register('tell.agent_definitions', new UseAgentDefinitions(
            registry: $definitions,
            validator: new AgentDefinitionValidator($capabilities, $tools),
        ));

        $loop = (new DefinitionLoopFactory(
            capabilities: $capabilities,
            tools: $tools,
        ))->instantiateAgentLoop($definition);
        $loop = match ($this->driver) {
            null => $loop,
            default => $loop->withDriver($this->driver),
        };
        $loop = $this->filterTools($loop, $options->tools);
        $loop = $this->withExecutionPolicy($loop, $policy, $options->directory);
        $loop = $this->withCooperativeCancellation($loop, $cancellation);

        return match ($this->decorateLoop) {
            null => $loop,
            default => ($this->decorateLoop)($loop),
        };
    }

    public function sessions(): SessionRuntime
    {
        return new SessionRuntime(
            sessions: $this->sessionRepository(),
            events: new EventDispatcher('tell-sessions'),
        );
    }

    public function sessionRepository(): SessionRepository
    {
        (new TellStorage($this->paths))->ensureSessions();

        return new SessionRepository(new FileSessionStore($this->paths->sessions));
    }

    public function attachExecutionTrace(AgentLoop $loop, TellOptions $options): void
    {
        (new ExecutionTraceWriter($this->paths, $this->config(), $options))->attach($loop);
    }

    public function config(): TellConfig
    {
        return TellConfig::fromFile($this->paths->configFile);
    }

    public function credentials(): TellCredentialStore
    {
        return new TellCredentialStore($this->paths);
    }

    public function secretResolver(string $projectPath): SecretResolver
    {
        return new SecretResolver(
            new EnvironmentSecretSource,
            DotenvFileSecretSource::optional(
                rtrim($projectPath, '/\\').DIRECTORY_SEPARATOR.'.env',
                'workspace-env',
            ),
            $this->credentials()->source(),
        );
    }

    public function paths(): TellPaths
    {
        return $this->paths;
    }

    public function workspace(): WorkspaceManager
    {
        return new WorkspaceManager($this->startupScans);
    }

    public function assertReady(TellOptions $options): void
    {
        if ($this->driver !== null) {
            $this->assertReasoningSupportedWithoutCredentials($options);

            return;
        }
        if ($options->dsn !== '') {
            return;
        }

        $this->assertCredentialAvailable($this->llmConfig($options), $options->connection);
    }

    private function llmConfig(TellOptions $options): LLMConfig
    {
        if ($this->modelResolver !== null) {
            return $this->modelResolver->resolve(TellRequest::fromOptions($options));
        }
        if ($options->dsn !== '') {
            return $this->withReasoning(
                LLMConfig::fromArray(Dsn::fromString($options->dsn)->toArray()),
                $options,
            );
        }

        TellCredentialNames::forProvider($options->connection);
        $config = LLMConfig::fromPreset(
            preset: $options->connection,
            basePath: $this->connectionDirectory($options),
            template: new EnvTemplate($this->secretResolver($options->directory)),
        );

        $config = match ($options->model) {
            '' => $config,
            default => $config->withOverrides(['model' => $options->model]),
        };

        return $this->withReasoning($config, $options);
    }

    private function withReasoning(LLMConfig $config, TellOptions $options): LLMConfig
    {
        if ($options->reasoningEffort === null) {
            return $config;
        }
        TellReasoningSupport::assertSupported($config->driver, $config->model, $options->reasoningEffort);

        return $config->withOverrides([
            'options' => [
                ...$config->options,
                ...TellReasoningSupport::options($config->driver, $options->reasoningEffort),
            ],
        ]);
    }

    private function assertReasoningSupportedWithoutCredentials(TellOptions $options): void
    {
        if ($options->reasoningEffort === null) {
            return;
        }
        if ($options->dsn !== '') {
            $config = LLMConfig::fromArray(Dsn::fromString($options->dsn)->toArray());
            TellReasoningSupport::assertSupported($config->driver, $config->model, $options->reasoningEffort);

            return;
        }

        $resolved = (new TellProviderCatalogue($this->paths))->resolve(
            $options->directory,
            $options->connection,
            $options->model,
        );
        $driver = is_string($resolved['provider'] ?? null) ? $resolved['provider'] : $options->connection;
        $model = is_string($resolved['model'] ?? null) ? $resolved['model'] : $options->model;
        TellReasoningSupport::assertSupported($driver, $model, $options->reasoningEffort);
    }

    private function connectionDirectory(TellOptions $options): ?string
    {
        $project = rtrim($options->directory, '/\\').'/config/llm/presets';

        return match (true) {
            is_file($project.'/'.$options->connection.'.yaml') => $project,
            is_file($this->paths->connections.'/'.$options->connection.'.yaml') => $this->paths->connections,
            default => null,
        };
    }

    private function assertCredentialAvailable(LLMConfig $config, string $connection): void
    {
        $host = parse_url($config->apiUrl, PHP_URL_HOST);
        $local = $config->driver === 'ollama'
            || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($config->apiKey !== '' || $local) {
            return;
        }
        $variable = TellCredentialNames::forProvider($connection);

        throw new RuntimeException(
            "Missing credential {$variable} for connection '{$connection}'. "
            ."Set it in the process environment, workspace .env, or with `tell auth set {$connection} --stdin`.",
        );
    }

    /** @param list<string> $allowed */
    private function filterTools(AgentLoop $loop, array $allowed): AgentLoop
    {
        if ($allowed === []) {
            return $loop;
        }
        $selected = [];
        foreach ($loop->tools()->all() as $name => $tool) {
            if (in_array($name, $allowed, true)) {
                $selected[] = $tool;
            }
        }

        return $loop->withTools(new Tools(...$selected));
    }

    /**
     * Bash caps its own output before any hook can see it, so spilling only
     * has a whole result to store if the tool is told to keep one. The caps
     * become the spill ceiling, and fall back to the retained-bytes limit when
     * spilling is off.
     */
    private function bashPolicy(TellExecutionPolicy $policy): BashPolicy
    {
        $bytes = $policy->spillsToolOutput() ? $policy->maxSpillBytes : $policy->maxToolOutputChars;

        return new BashPolicy(
            maxOutputChars: $bytes,
            headChars: intdiv($bytes, 2),
            tailChars: $bytes - intdiv($bytes, 2),
            timeout: max(1, (int) ceil($policy->timeoutMs / 1_000)),
            stdoutLimitBytes: $bytes,
            stderrLimitBytes: $bytes,
        );
    }

    private function withExecutionPolicy(AgentLoop $loop, TellExecutionPolicy $policy, string $directory): AgentLoop
    {
        $driver = $loop->driver();
        if ($driver instanceof ToolCallingDriver) {
            $loop = $loop->withDriver($driver->withRetryPolicy(new InferenceRetryPolicy(
                maxAttempts: $policy->maxRetries + 1,
            )));
        }
        $interceptor = $loop->interceptor();
        if (! $interceptor instanceof HookStack) {
            throw new RuntimeException('Tell requires the Agents hook-stack lifecycle interceptor.');
        }

        $interceptor = $interceptor->with(
            hook: new TellExecutionBudgetHook($policy, $this->clock),
            triggerTypes: HookTriggers::of(
                HookTrigger::BeforeExecution,
                HookTrigger::BeforeStep,
                HookTrigger::BeforeToolUse,
                HookTrigger::AfterToolUse,
                HookTrigger::AfterStep,
            ),
            priority: 300,
            name: 'tell:execution_budget',
        );
        if ($policy->spillsToolOutput()) {
            // Above the budget hook, which would otherwise truncate the result
            // this one exists to preserve.
            $interceptor = $interceptor->with(
                hook: new TellSpillToolOutputHook(ToolOutputSpill::fromPolicy($directory, $policy)),
                triggerTypes: HookTriggers::of(HookTrigger::AfterToolUse),
                priority: 400,
                name: 'tell:spill_tool_output',
            );
        }

        return $loop->withInterceptor($interceptor);
    }

    private function withCooperativeCancellation(
        AgentLoop $loop,
        ?CanProvideCancellationSignal $cancellation,
    ): AgentLoop {
        if ($cancellation === null) {
            return $loop;
        }
        $interceptor = $loop->interceptor();
        if (! $interceptor instanceof HookStack) {
            throw new RuntimeException('Tell requires the Agents hook-stack lifecycle interceptor.');
        }

        return $loop->withInterceptor($interceptor->with(
            hook: new CooperativeCancellationHook($cancellation),
            triggerTypes: HookTriggers::of(
                HookTrigger::BeforeExecution,
                HookTrigger::BeforeStep,
            ),
            priority: 250,
            name: 'tell:cooperative_cancellation',
        ));
    }
}
