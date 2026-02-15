<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Factory;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\CanControlAgentLoop;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Capability\CanManageAgentCapabilities;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Tool\Contracts\CanManageTools;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;
use InvalidArgumentException;

final readonly class DefinitionLoopFactory implements CanInstantiateAgentLoop
{
    public function __construct(
        private CanManageAgentCapabilities $capabilities,
        private ?CanManageTools $tools = null,
        private ?CanHandleEvents $events = null,
    ) {}

    #[\Override]
    public function instantiateAgentLoop(AgentDefinition $definition): CanControlAgentLoop {
        $builder = AgentBuilder::base($this->events);
        $builder = $this->withLlmConfig($builder, $definition);
        $builder = $this->withGuards($builder, $definition);
        $builder = $this->withCapabilities($builder, $definition);
        $builder = $this->withTools($builder, $definition);
        return $builder->build();
    }

    // INTERNALS ////////////////////////////////////////////////////

    private function withLlmConfig(AgentBuilder $builder, AgentDefinition $definition): AgentBuilder {
        $llm = match (true) {
            $definition->llmConfig instanceof LLMConfig => LLMProvider::new()->withConfig($definition->llmConfig),
            is_string($definition->llmConfig) && $definition->llmConfig !== '' => LLMProvider::using($definition->llmConfig),
            default => null,
        };

        if ($llm === null) {
            return $builder;
        }

        return $builder->withCapability(
            new UseDriver(new ToolCallingDriver(llm: $llm))
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
            if (!$this->requiresToolRegistry($definition)) {
                return $builder;
            }

            throw new InvalidArgumentException(
                "Definition '{$definition->name}' declares tools, but no CanManageTools was provided."
            );
        }

        $selectedNames = $this->selectToolNames($definition, $this->tools->names());
        $unknown = $this->unknownToolNames($selectedNames);
        if ($unknown !== []) {
            $unknownNames = implode(', ', $unknown);
            throw new InvalidArgumentException(
                "Definition '{$definition->name}' references unknown tools: {$unknownNames}"
            );
        }

        $resolvedTools = $this->resolveTools($selectedNames);
        if ($resolvedTools->isEmpty()) {
            return $builder;
        }

        return $builder->withCapability(new UseTools(...array_values($resolvedTools->all())));
    }

    private function resolveCapability(string $name): CanProvideAgentCapability {
        if ($this->capabilities->has($name)) {
            return $this->capabilities->get($name);
        }

        $available = implode(', ', $this->capabilities->names());
        throw new InvalidArgumentException(
            "Capability '{$name}' is not registered. Available: {$available}"
        );
    }

    private function requiresToolRegistry(AgentDefinition $definition): bool {
        if ($definition->tools !== null && !$definition->tools->isEmpty()) {
            return true;
        }

        if ($definition->toolsDeny !== null && !$definition->toolsDeny->isEmpty()) {
            return true;
        }

        return false;
    }

    /**
     * @param array<int, string> $available
     * @return array<int, string>
     */
    private function selectToolNames(AgentDefinition $definition, array $available): array {
        $selected = match (true) {
            $definition->inheritsAllTools() => $available,
            default => $definition->tools?->all() ?? [],
        };

        if ($definition->toolsDeny === null || $definition->toolsDeny->isEmpty()) {
            return $selected;
        }

        $denied = array_flip($definition->toolsDeny->all());
        $filtered = [];
        foreach ($selected as $name) {
            if (!isset($denied[$name])) {
                $filtered[] = $name;
            }
        }
        return $filtered;
    }

    /**
     * @param array<int, string> $selectedNames
     * @return array<int, string>
     */
    private function unknownToolNames(array $selectedNames): array {
        if ($this->tools === null) {
            return [];
        }

        $unknown = [];
        foreach ($selectedNames as $name) {
            if (!$this->tools->has($name)) {
                $unknown[] = $name;
            }
        }
        return $unknown;
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
