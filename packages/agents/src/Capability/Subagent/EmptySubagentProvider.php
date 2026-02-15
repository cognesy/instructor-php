<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Subagent;

use Cognesy\Agents\Exceptions\AgentNotFoundException;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Template\Data\AgentDefinition;

final class EmptySubagentProvider implements CanManageAgentDefinitions
{
    #[\Override]
    public function has(string $name): bool {
        return false;
    }

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

    #[\Override]
    public function register(AgentDefinition $definition): void {}

    #[\Override]
    public function registerMany(AgentDefinition ...$definitions): void {}

    #[\Override]
    public function loadFromFile(string $path): void {}

    #[\Override]
    public function loadFromDirectory(string $path, bool $recursive = false): void {}

    #[\Override]
    public function autoDiscover(
        string $projectPath,
        ?string $packagePath = null,
        ?string $userPath = null,
    ): void {}

    /** @return array<string, string> */
    #[\Override]
    public function errors(): array {
        return [];
    }
}
