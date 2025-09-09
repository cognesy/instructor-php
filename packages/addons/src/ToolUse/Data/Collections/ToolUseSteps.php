<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data\Collections;

use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Iterator;

final readonly class ToolUseSteps
{
    /** @var ToolUseStep[] */
    private array $steps;

    public function __construct(ToolUseStep ...$steps) {
        $this->steps = $steps;
    }

    public function withSteps(ToolUseStep ...$steps) : self {
        return new self(...$steps);
    }

    public function withAddedStep(ToolUseStep $step) : self {
        return new self(...[...$this->steps, $step]);
    }

    public function all() : array {
        return $this->steps;
    }

    public function each() : Iterator {
        foreach ($this->steps as $step) {
            yield $step;
        }
    }

    public function count() : int {
        return count($this->steps);
    }

    public function isEmpty() : bool {
        return empty($this->steps);
    }

    public function reversed() : Iterator {
        foreach (array_reverse($this->steps) as $step) {
            yield $step;
        }
    }
}