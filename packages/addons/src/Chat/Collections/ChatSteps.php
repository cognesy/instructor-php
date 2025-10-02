<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Collections;

use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\StepByStep\Collections\Steps;

/** @extends Steps<ChatStep> */
final readonly class ChatSteps extends Steps
{
    public function __construct(ChatStep ...$steps) {
        parent::__construct(...$steps);
    }

    public static function fromArray(array $data): self {
        $steps = array_map(fn(array $stepData) => ChatStep::fromArray($stepData), $data);
        return new self(...$steps);
    }

    public function toArray(): array {
        return array_map(fn(ChatStep $step) => $step->toArray(), $this->all());
    }

    #[\Override]
    public function currentStep(): ?ChatStep {
        /** @var ?ChatStep */
        return parent::currentStep();
    }

    #[\Override]
    public function stepAt(int $index): ?ChatStep {
        /** @var ?ChatStep */
        return parent::stepAt($index);
    }

}
