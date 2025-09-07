<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data\Collections;

use Cognesy\Addons\Chat\Data\ChatStep;

final class ChatSteps
{
    /** @var ChatStep[] */
    private array $steps;

    public function __construct(ChatStep ...$steps) {
        $this->steps = $steps;
    }

    public function add(ChatStep ...$steps): self {
        return new self(...array_merge($this->steps, $steps));
    }

    public function count(): int {
        return count($this->steps);
    }

    public function isEmpty(): bool {
        return $this->steps === [];
    }

    public function last(): ?ChatStep {
        if ($this->isEmpty()) {
            return null;
        }
        return $this->steps[count($this->steps) - 1] ?? null;
    }

    /** @return ChatStep[] */
    public function all(): array {
        return $this->steps;
    }

    public function toArray(): array {
        return array_map(fn(ChatStep $step) => $step->toArray(), $this->steps);
    }

    public static function fromArray(array $data): self {
        $steps = array_map(fn(array $stepData) => ChatStep::fromArray($stepData), $data);
        return new self(...$steps);
    }
}

