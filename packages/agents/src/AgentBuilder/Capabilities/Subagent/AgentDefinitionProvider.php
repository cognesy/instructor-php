<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent;

use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;

interface AgentDefinitionProvider
{
    public function get(string $name): AgentDefinition;

    /** @return array<string, AgentDefinition> */
    public function all(): array;

    /** @return array<int, string> */
    public function names(): array;

    public function count(): int;
}
