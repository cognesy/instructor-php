<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support;

use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\AgentDefinitionProvider;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Agents\Exceptions\AgentNotFoundException;

final class FakeSubagentProvider implements AgentDefinitionProvider
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
}
