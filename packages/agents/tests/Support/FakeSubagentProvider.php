<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support;

use Cognesy\Agents\Exceptions\AgentNotFoundException;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Template\Data\AgentDefinition;

final class FakeSubagentProvider implements CanManageAgentDefinitions
{
    /** @var array<string, AgentDefinition> */
    private array $specs;

    public function __construct(AgentDefinition ...$specs) {
        $this->specs = [];
        foreach ($specs as $spec) {
            $this->specs[$spec->name] = $spec;
        }
    }

    #[\Override]
    public function has(string $name): bool {
        return isset($this->specs[$name]);
    }

    #[\Override]
    public function get(string $name): AgentDefinition {
        if (!isset($this->specs[$name])) {
            $available = implode(', ', $this->names());
            throw new AgentNotFoundException(
                "Agent '{$name}' not found. Available: {$available}"
            );
        }
        return $this->specs[$name];
    }

    #[\Override]
    public function all(): array {
        return $this->specs;
    }

    #[\Override]
    public function names(): array {
        return array_keys($this->specs);
    }

    #[\Override]
    public function count(): int {
        return count($this->specs);
    }

    #[\Override]
    public function register(AgentDefinition $definition): void {
        $this->specs[$definition->name] = $definition;
    }

    #[\Override]
    public function registerMany(AgentDefinition ...$definitions): void {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

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
