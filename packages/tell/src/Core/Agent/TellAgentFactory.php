<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Agent;

use Closure;
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Capability\Cancellation\CooperativeCancellationHook;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;
use Cognesy\Tell\Core\Secrets\TellCredentialNames;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;
use Cognesy\Tell\Core\Contract\Agent\CanDescribeTellDelegation;
use Cognesy\Tell\Core\Contract\Agent\CanLoadTellAgentDefinitions;
use Cognesy\Tell\Core\Contract\Agent\CanRecordTellAgentDiagnostics;
use Cognesy\Tell\Core\Contract\Execution\CanReadTellClock;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Data\TellExecutionPolicy;
use Cognesy\Tell\Data\TellRequest;
use InvalidArgumentException;
use RuntimeException;

/** Builds an agent only from services and ordered contributions selected by its host. */
final readonly class TellAgentFactory implements CanBuildTellAgent
{
    /** @var Closure(AgentLoop): AgentLoop|null */
    private ?Closure $decorateLoop;

    /**
     * @param list<CanContributeTellAgent> $contributions
     * @param callable(AgentLoop): AgentLoop|null $decorateLoop
     */
    public function __construct(
        private TellPaths $paths,
        private CanTraceTellExecution $tracer,
        private CanReadTellClock $clock,
        private CanResolveTellModel $modelResolver,
        private CanLoadTellAgentDefinitions $definitionLoader,
        private array $contributions,
        ?callable $decorateLoop = null,
        private ?CanUseTools $driver = null,
    ) {
        $this->decorateLoop = match ($decorateLoop) {
            null => null,
            default => Closure::fromCallable($decorateLoop),
        };
    }

    #[\Override]
    public function definitions(string $projectPath): AgentDefinitionRegistry {
        return $this->definitionLoader->definitions($projectPath);
    }

    #[\Override]
    public function definition(TellRequest $request): AgentDefinition {
        $definition = $this->definitions($request->directory)->get($request->agent);

        return $this->configureDefinition($definition, $request);
    }

    public function configureDefinition(AgentDefinition $definition, TellRequest $request): AgentDefinition {
        $llmConfig = match ($this->driver) {
            null => $this->modelResolver->resolve($request),
            default => null,
        };
        $reasoningConfig = $llmConfig ?? match ($request->reasoningEffort) {
            null => null,
            default => $this->modelResolver->resolve($request),
        };
        if ($reasoningConfig !== null) {
            $this->assertReasoningSupported($reasoningConfig->driver, $reasoningConfig->model, $request);
        }
        if ($llmConfig !== null && $request->dsn === '') {
            $this->assertCredentialAvailable($llmConfig, $request->connection);
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
            budget: new ExecutionBudget(maxSteps: $request->maxSteps),
            metadata: $definition->metadata,
        );
    }

    #[\Override]
    public function build(
        TellRequest $request,
        ?CanProvideCancellationSignal $cancellation = null,
        ?AgentDefinition $definition = null,
        ?CanDescribeTellDelegation $delegation = null,
        ?CanRecordTellAgentDiagnostics $diagnostics = null,
    ): AgentLoop {
        $definition ??= $this->definition($request);
        $definitions = $this->definitions($request->directory);
        $capabilities = new AgentCapabilityRegistry();
        $tools = new ToolRegistry();
        $assembly = new TellAgentAssembly(
            $request,
            $definitions,
            $capabilities,
            $tools,
            $this,
            $this->tracer,
            $delegation,
            $diagnostics,
        );
        foreach ($this->contributions as $contribution) {
            $contribution->contribute($assembly);
        }

        $loop = (new DefinitionLoopFactory($capabilities, $tools))->instantiateAgentLoop($definition);
        $loop = match ($this->driver) {
            null => $loop,
            default => $loop->withDriver($this->driver),
        };
        $policy = $request->policy ?? TellExecutionPolicy::defaults();
        $blobs = $policy->spillsToolOutput() ? $this->paths->blobsFor($request->directory) : null;
        $loop = $this->withReasoningSelection($loop, $request);
        $loop = $this->filterTools($loop, $request->tools);
        $loop = $this->withExecutionPolicy($loop, $policy, $blobs);
        $loop = $this->withCooperativeCancellation($loop, $cancellation);

        return match ($this->decorateLoop) {
            null => $loop,
            default => ($this->decorateLoop)($loop),
        };
    }

    public function paths(): TellPaths {
        return $this->paths;
    }

    /** @param callable(AgentLoop): AgentLoop $decorateLoop */
    public function withLoopDecorator(callable $decorateLoop): self {
        return new self(
            $this->paths,
            $this->tracer,
            $this->clock,
            $this->modelResolver,
            $this->definitionLoader,
            $this->contributions,
            $decorateLoop,
            $this->driver,
        );
    }

    #[\Override]
    public function assertReady(TellRequest $request): void {
        if ($this->driver !== null && $request->reasoningEffort === null) {
            return;
        }
        $config = $this->modelResolver->resolve($request);
        $this->assertReasoningSupported($config->driver, $config->model, $request);
        if ($this->driver !== null || $request->dsn !== '') {
            return;
        }

        $this->assertCredentialAvailable($config, $request->connection);
    }

    private function withReasoningSelection(AgentLoop $loop, TellRequest $request): AgentLoop {
        if ($request->reasoningEffort === null) {
            return $loop;
        }
        $driver = $loop->driver();
        if (!$driver instanceof ToolCallingDriver) {
            return $loop;
        }

        return $loop->withDriver($driver->withReasoning(
            ReasoningSelection::effort($request->reasoningEffort),
        ));
    }

    private function assertReasoningSupported(string $driver, string $model, TellRequest $request): void {
        if ($request->reasoningEffort === null) {
            return;
        }
        $selection = ReasoningSelection::effort($request->reasoningEffort);
        $capabilities = BundledInferenceDrivers::capabilities($driver, $model)?->reasoning();
        if ($capabilities?->supports($selection) === true) {
            return;
        }

        $label = $model === '' ? $driver : "{$driver}/{$model}";
        throw new InvalidArgumentException(
            "Reasoning effort is not supported by '{$label}' according to Polyglot capability metadata.",
        );
    }

    private function assertCredentialAvailable(LLMConfig $config, string $connection): void {
        $host = parse_url($config->apiUrl, PHP_URL_HOST);
        $local = $config->driver === 'ollama' || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($config->apiKey !== '' || $local) {
            return;
        }
        $variable = TellCredentialNames::forProvider($connection);

        throw new RuntimeException(
            "Missing credential {$variable} for connection '{$connection}'. "
            . "Set it in the process environment, workspace .env, or with `tell auth set {$connection} --stdin`.",
        );
    }

    /** @param list<string> $allowed */
    private function filterTools(AgentLoop $loop, array $allowed): AgentLoop {
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

    private function withExecutionPolicy(AgentLoop $loop, TellExecutionPolicy $policy, ?string $blobs): AgentLoop {
        $driver = $loop->driver();
        if ($driver instanceof ToolCallingDriver) {
            $loop = $loop->withDriver($driver->withRetryPolicy(new InferenceRetryPolicy(
                maxAttempts: $policy->maxRetries + 1,
            )));
        }
        $interceptor = $loop->interceptor();
        if (!$interceptor instanceof HookStack) {
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
        if ($blobs !== null) {
            $interceptor = $interceptor->with(
                hook: new TellSpillToolOutputHook(ToolOutputSpill::fromPolicy($blobs, $policy)),
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
        if (!$interceptor instanceof HookStack) {
            throw new RuntimeException('Tell requires the Agents hook-stack lifecycle interceptor.');
        }

        return $loop->withInterceptor($interceptor->with(
            hook: new CooperativeCancellationHook($cancellation),
            triggerTypes: HookTriggers::of(HookTrigger::BeforeExecution, HookTrigger::BeforeStep),
            priority: 250,
            name: 'tell:cooperative_cancellation',
        ));
    }
}
