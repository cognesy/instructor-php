<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data\Collections;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

final readonly class StepProcessors
{
    /** @var CanProcessToolState[] */
    private array $processors;

    public function __construct(CanProcessToolState ...$processors) {
        $this->processors = $processors;
    }

    public function withProcessors(CanProcessToolState ...$processors) : self {
        return new self(...$processors);
    }

    public function isEmpty() : bool {
        return $this->processors === [];
    }

    public function apply(ToolUseStep $step, ToolUseState $state) : ToolUseState {
        foreach ($this->processors as $processor) {
            $state = $processor->processStep($step, $state);
        }
        return $state;
    }
}

