<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data\Collections;

use Cognesy\Addons\Chat\Contracts\CanProcessMessages;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Messages;

final class MessageProcessors
{
    /** @var CanProcessMessages[] */
    private array $processors;

    public function __construct(CanProcessMessages ...$processors) {
        $this->processors = $processors;
    }

    public function add(CanProcessMessages ...$processors): self {
        foreach ($processors as $processor) {
            $this->processors[] = $processor;
        }
        return $this;
    }

    public function isEmpty(): bool {
        return $this->processors === [];
    }

    public function apply(Messages $messages, ChatState $state): Messages {
        $currentMessages = $messages;
        foreach ($this->processors as $processor) {
            $currentMessages = $processor->process($currentMessages, $state);
        }
        return $currentMessages;
    }

    /** @return CanProcessMessages[] */
    public function all(): array {
        return $this->processors;
    }
}
