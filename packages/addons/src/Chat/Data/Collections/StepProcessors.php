<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data\Collections;

use Cognesy\Addons\Chat\Contracts\CanProcessChatStep;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;

final class StepProcessors
{
    /** @var CanProcessChatStep[] */
    private array $processors = [];

    public function __construct(CanProcessChatStep ...$processors) {
        $this->processors = $processors;
    }

    public function add(CanProcessChatStep ...$processors): self {
        foreach ($processors as $processor) {
            $this->processors[] = $processor;
        }
        return $this;
    }

    public function isEmpty(): bool {
        return $this->processors === [];
    }

    public function apply(ChatStep $step, ChatState $state): ChatState {
        $currentState = $state;
        foreach ($this->processors as $processor) {
            $currentState = $processor->process($step, $currentState);
        }
        return $currentState;
    }

    /** @return CanProcessChatStep[] */
    public function all(): array {
        return $this->processors;
    }
}
