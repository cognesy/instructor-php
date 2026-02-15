<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Builder\Contracts\CanComposeAgentLoop;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Events\Contracts\CanHandleEvents;

/**
 * Composition layer for assembling AgentLoop instances via capabilities.
 */
final readonly class AgentBuilder implements CanComposeAgentLoop
{
    /** @var list<CanProvideAgentCapability> */
    private array $capabilities;

    private function __construct(
        private ?CanHandleEvents $parentEvents = null,
        array $capabilities = [],
    ) {
        $this->capabilities = $capabilities;
    }

    public static function base(?CanHandleEvents $parentEvents = null): self {
        return new self(parentEvents: $parentEvents);
    }

    #[\Override]
    public function withCapability(CanProvideAgentCapability $capability): self {
        return new self(
            parentEvents: $this->parentEvents,
            capabilities: [...$this->capabilities, $capability],
        );
    }

    #[\Override]
    public function build(): AgentLoop {
        $installer = AgentConfigurator::base(parentEvents: $this->parentEvents);
        foreach ($this->capabilities as $capability) {
            $installer = $installer->install($capability);
        }
        return $installer->toAgentLoop();
    }
}
