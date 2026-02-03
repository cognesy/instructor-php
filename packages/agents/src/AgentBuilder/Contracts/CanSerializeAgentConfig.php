<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Contracts;

/**
 * Opt-in interface for agents that support configuration serialization.
 */
interface CanSerializeAgentConfig
{
    public function serializeConfig(): array;

    public static function fromConfig(array $config): static;
}
