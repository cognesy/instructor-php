<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Factory;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Capability\CanManageAgentCapabilities;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Profile\AgentIdentity;
use Cognesy\Agents\Template\AgentDefinitionReferenceRules;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Tool\Contracts\CanManageTools;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use InvalidArgumentException;
use Override;

final readonly class DefinitionLoopFactory implements CanInstantiateAgentLoop
{
    private AgentDefinitionReferenceRules $references;

    public function __construct(
        private CanManageAgentCapabilities $capabilities,
        private ?CanManageTools $tools = null,
        private ?CanHandleEvents $events = null,
    ) {
        $this->references = new AgentDefinitionReferenceRules($capabilities, $tools);
    }

    #[Override]
    public function instantiateAgentLoop(AgentDefinition $definition): AgentLoop {
        $builder = AgentBuilder::base($this->events)->withIdentity(new AgentIdentity(
            name: $definition->name,
            description: $definition->description,
        ));
        $builder = $this->withLLMConfig($builder, $definition);
        $builder = $this->withGuards($builder, $definition);
        $builder = $this->withCapabilities($builder, $definition);
        $builder = $this->withTools($builder, $definition);
        return $builder->build();
    }

    // INTERNALS ////////////////////////////////////////////////////

    private function withLLMConfig(AgentBuilder $builder, AgentDefinition $definition): AgentBuilder {
        $llm = match (true) {
            $definition->llmConfig instanceof LLMConfig => LLMProvider::new()->withLLMConfig($definition->llmConfig),
            is_string($definition->llmConfig) && $definition->llmConfig !== '' => LLMProvider::new()
                ->withConfigOverrides(['driver' => $definition->llmConfig]),
            default => null,
        };

        if ($llm === null) {
            return $builder;
        }

        return $builder->withCapability(
            new UseDriver(new ToolCallingDriver(
                llm: $llm,
                events: $this->events,
                inference: InferenceRuntime::fromProvider($llm, events: $this->events),
            )),
        );
    }

    private function withGuards(AgentBuilder $builder, AgentDefinition $definition): AgentBuilder {
        $budget = $definition->budget();
        if ($budget->isEmpty()) {
            return $builder;
        }

        return $builder->withCapability(new UseGuards(
            maxSteps: $budget->maxSteps,
            maxTokens: $budget->maxTokens,
            maxExecutionTime: $budget->maxSeconds,
        ));
    }

    private function withCapabilities(AgentBuilder $builder, AgentDefinition $definition): AgentBuilder {
        $nextBuilder = $builder;
        foreach ($definition->capabilities->all() as $capabilityName) {
            $capability = $this->resolveCapability($capabilityName);
            $nextBuilder = $nextBuilder->withCapability($capability);
        }

        return $nextBuilder;
    }

    private function withTools(AgentBuilder $builder, AgentDefinition $definition): AgentBuilder {
        if ($this->tools === null) {
            if (!$this->references->requiresToolRegistry($definition)) {
                return $builder;
            }

            throw new InvalidArgumentException(
                "Definition '{$definition->name}' declares tools, but no CanManageTools was provided.",
            );
        }

        $selectedNames = $this->references->selectedToolNames($definition)->all();
        $unknown = $this->references->unknownTools($definition)->all();
        if ($unknown !== []) {
            $unknownNames = implode(', ', $unknown);
            throw new InvalidArgumentException(
                "Definition '{$definition->name}' references unknown tools: {$unknownNames}",
            );
        }

        $resolvedTools = $this->resolveTools($selectedNames);
        if ($resolvedTools->isEmpty()) {
            return $builder;
        }

        return $builder->withCapability(new UseTools(...array_values($resolvedTools->all())));
    }

    private function resolveCapability(string $name): CanProvideAgentCapability {
        if ($this->references->hasCapability($name)) {
            return $this->capabilities->get($name);
        }

        $available = implode(', ', $this->capabilities->names());
        throw new InvalidArgumentException(
            "Capability '{$name}' is not registered. Available: {$available}. "
            . 'Capabilities declared by installed packages are not loaded automatically; '
            . 'call CapabilityDiscovery::discover() during bootstrap.',
        );
    }

    /**
     * @param array<int, string> $names
     */
    private function resolveTools(array $names): Tools {
        if ($this->tools === null) {
            return new Tools();
        }

        $resolved = [];
        foreach ($names as $name) {
            if (!$this->tools->has($name)) {
                continue;
            }

            $resolved[] = $this->tools->get($name);
        }

        return new Tools(...$resolved);
    }
}
