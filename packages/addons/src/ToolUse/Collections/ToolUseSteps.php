<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Collections;

use Cognesy\Addons\Core\Collections\Steps;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

/** @extends Steps<ToolUseStep> */
final readonly class ToolUseSteps extends Steps
{
    public function __construct(ToolUseStep ...$steps) {
        parent::__construct(...$steps);
    }

    public static function fromArray(array $data): self {
        $steps = array_map(fn($stepData) => ToolUseStep::fromArray($stepData), $data);
        return new self(...$steps);
    }

    public function toArray(): array {
        return array_map(fn(ToolUseStep $step) => $step->toArray(), $this->all());
    }

    public function currentStep(): ?ToolUseStep {
        /** @var ?ToolUseStep */
        return parent::currentStep();
    }

    public function stepAt(int $index): ?ToolUseStep {
        /** @var ?ToolUseStep */
        return parent::stepAt($index);
    }

}
