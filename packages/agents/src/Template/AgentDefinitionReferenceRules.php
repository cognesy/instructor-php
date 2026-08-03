<?php declare(strict_types=1);

namespace Cognesy\Agents\Template;

use Cognesy\Agents\Capability\CanManageAgentCapabilities;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Tool\Contracts\CanManageTools;

final readonly class AgentDefinitionReferenceRules
{
    public function __construct(
        private CanManageAgentCapabilities $capabilities,
        private ?CanManageTools $tools = null,
    ) {}

    public function hasCapability(string $name): bool {
        return $this->capabilities->has($name);
    }

    public function requiresToolRegistry(AgentDefinition $definition): bool {
        return match (true) {
            $definition->tools !== null && !$definition->tools->isEmpty() => true,
            $definition->toolsDeny !== null && !$definition->toolsDeny->isEmpty() => true,
            default => false,
        };
    }

    public function unknownCapabilities(AgentDefinition $definition): NameList {
        $unknown = [];
        foreach ($definition->capabilities->all() as $name) {
            if (!$this->capabilities->has($name)) {
                $unknown[] = $name;
            }
        }
        return new NameList(...$unknown);
    }

    public function selectedToolNames(AgentDefinition $definition): NameList {
        $available = $this->tools?->names() ?? [];
        $selected = match (true) {
            $definition->inheritsAllTools() => $available,
            default => $definition->tools?->all() ?? [],
        };
        if ($definition->toolsDeny === null || $definition->toolsDeny->isEmpty()) {
            return new NameList(...$selected);
        }

        $denied = array_flip($definition->toolsDeny->all());
        $filtered = [];
        foreach ($selected as $name) {
            if (!isset($denied[$name])) {
                $filtered[] = $name;
            }
        }
        return new NameList(...$filtered);
    }

    public function unknownTools(AgentDefinition $definition): NameList {
        if ($this->tools === null) {
            return new NameList();
        }

        $declared = [
            ...($definition->tools?->all() ?? []),
            ...($definition->toolsDeny?->all() ?? []),
        ];
        $unknown = [];
        foreach ($declared as $name) {
            if (!$this->tools->has($name)) {
                $unknown[] = $name;
            }
        }
        return new NameList(...$unknown);
    }
}
