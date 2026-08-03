<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Agents\Hook\Enums\HookTrigger;

final class HookContractViolated extends AgentEvent
{
    public function __construct(
        public readonly HookTrigger $trigger,
        public readonly ?string $hookName,
        public readonly string $field,
    ) {
        parent::__construct([
            'triggerType' => $this->trigger->value,
            'hookName' => $this->hookName,
            'field' => $this->field,
        ]);
    }
}
