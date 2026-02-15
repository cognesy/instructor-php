<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Contracts\HookInterface;

final readonly class UseHook implements CanProvideAgentCapability
{
    public function __construct(
        private HookInterface $hook,
        private HookTriggers $triggers,
        private int $priority = 0,
        private ?string $name = null,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_hook';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $hooks = $agent->hooks()->with(
            hook: $this->hook,
            triggerTypes: $this->triggers,
            priority: $this->priority,
            name: $this->name,
        );
        return $agent->withHooks($hooks);
    }
}
