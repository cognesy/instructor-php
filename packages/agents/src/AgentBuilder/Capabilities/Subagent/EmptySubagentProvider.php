<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent;

use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Agents\Exceptions\AgentNotFoundException;

final class EmptySubagentProvider implements AgentDefinitionProvider
{
    #[\Override]
    public function get(string $name): AgentDefinition {
        $available = implode(', ', $this->names());
        throw new AgentNotFoundException(
            "Agent '{$name}' not found. Available: {$available}"
        );
    }

    #[\Override]
    public function all(): array {
        return [];
    }

    #[\Override]
    public function names(): array {
        return [];
    }

    #[\Override]
    public function count(): int {
        return 0;
    }
}
