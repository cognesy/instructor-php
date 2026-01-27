<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Data;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;

/**
 * Abstract base class for hook contexts.
 *
 * Provides common functionality for all context implementations:
 * - State access and modification
 * - Metadata storage and retrieval
 *
 * Subclasses must implement:
 * - eventType(): Return the specific event type
 * - withState(): Return a new instance with modified state
 */
abstract readonly class AbstractHookContext implements HookContext
{
    /**
     * @param AgentState $state The current agent state
     * @param array<string, mixed> $metadata Additional context metadata
     */
    public function __construct(
        protected AgentState $state,
        protected array $metadata = [],
    ) {}

    #[\Override]
    public function state(): AgentState
    {
        return $this->state;
    }

    #[\Override]
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get a specific metadata value.
     *
     * @param string $key The metadata key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The metadata value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if metadata key exists.
     *
     * @param string $key The metadata key
     * @return bool True if the key exists
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }
}
