<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Builder\Contracts\CanComposeAgentLoop;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Profile\AgentIdentity;
use Cognesy\Events\Contracts\CanHandleEvents;
use Override;

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
        private AgentIdentity $identity = new AgentIdentity('anonymous', ''),
        private bool $hookContractDiagnostics = false,
        private bool $strictHookContracts = false,
    ) {
        $this->capabilities = $capabilities;
    }

    public static function base(?CanHandleEvents $parentEvents = null): self {
        return new self(parentEvents: $parentEvents);
    }

    #[Override]
    public function withCapability(CanProvideAgentCapability $capability): self {
        return new self(
            parentEvents: $this->parentEvents,
            capabilities: [...$this->capabilities, $capability],
            identity: $this->identity,
            hookContractDiagnostics: $this->hookContractDiagnostics,
            strictHookContracts: $this->strictHookContracts,
        );
    }

    public function withIdentity(AgentIdentity $identity): self {
        return new self(
            parentEvents: $this->parentEvents,
            capabilities: $this->capabilities,
            identity: $identity,
            hookContractDiagnostics: $this->hookContractDiagnostics,
            strictHookContracts: $this->strictHookContracts,
        );
    }

    public function withHookContractDiagnostics(): self {
        return new self(
            parentEvents: $this->parentEvents,
            capabilities: $this->capabilities,
            identity: $this->identity,
            hookContractDiagnostics: true,
            strictHookContracts: $this->strictHookContracts,
        );
    }

    public function withStrictHookContracts(): self {
        return new self(
            parentEvents: $this->parentEvents,
            capabilities: $this->capabilities,
            identity: $this->identity,
            hookContractDiagnostics: true,
            strictHookContracts: true,
        );
    }

    #[Override]
    public function build(): AgentLoop {
        $installer = AgentConfigurator::base(
            parentEvents: $this->parentEvents,
            identity: $this->identity,
        );
        foreach ($this->capabilities as $capability) {
            $installer = $installer->install($capability);
        }
        $hooks = match (true) {
            $this->strictHookContracts => $installer->hooks()->strict(),
            $this->hookContractDiagnostics => $installer->hooks()->withContractDiagnostics(),
            default => $installer->hooks(),
        };
        $installer = $installer->withHooks($hooks);
        return $installer->toAgentLoop();
    }
}
