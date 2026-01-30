<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Hooks;

use Cognesy\Agents\Core\Data\AgentState;

final class HookStack
{
    private HookRegistry $registry;

    private int $order = 0;

    public function __construct(?HookRegistry $registry = null) {
        $this->registry = $registry ?? HookRegistry::empty();
    }

    public function with(Hook $hook, int $priority = 0): self {
        $clone = clone $this;
        $clone->registry = $clone->registry->with($hook, $priority, $clone->order++);
        return $clone;
    }

    public function on(HookType $type, AgentState $state): AgentState {
        foreach ($this->sortedHooks() as $entry) {
            $hook = $entry->hook;
            if (!in_array($type, $hook->appliesTo(), true)) {
                continue;
            }
            $state = $hook->on($type, $state);
        }

        return $state;
    }

    /**
     * @return list<HookRegistration>
     */
    private function sortedHooks(): array {
        return $this->registry->sorted();
    }
}
