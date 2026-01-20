<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Capabilities\Subagent;

use Cognesy\Addons\AgentBuilder\Capabilities\Subagent\SubagentDefinition;
use Cognesy\Addons\AgentBuilder\Capabilities\Subagent\SubagentProvider;
use Cognesy\Addons\Agent\Exceptions\AgentNotFoundException;

final class EmptySubagentProvider implements SubagentProvider
{
    #[\Override]
    public function get(string $name): SubagentDefinition {
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
