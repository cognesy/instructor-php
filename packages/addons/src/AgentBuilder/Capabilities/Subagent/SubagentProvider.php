<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Capabilities\Subagent;

interface SubagentProvider
{
    public function get(string $name): SubagentDefinition;

    /**
     * @return array<string, SubagentDefinition>
     */
    public function all(): array;

    /**
     * @return array<int, string>
     */
    public function names(): array;

    public function count(): int;
}
