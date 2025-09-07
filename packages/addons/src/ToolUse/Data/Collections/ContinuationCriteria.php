<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data\Collections;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinueToolUse;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

final class ContinuationCriteria
{
    /** @var CanDecideToContinueToolUse[] */
    private array $items = [];

    public function add(CanDecideToContinueToolUse ...$criteria) : self {
        foreach ($criteria as $criterion) {
            $this->items[] = $criterion;
        }
        return $this;
    }

    public function isEmpty() : bool {
        return $this->items === [];
    }

    public function canContinue(ToolUseState $state) : bool {
        foreach ($this->items as $criterion) {
            if (!$criterion->canContinue($state)) {
                return false;
            }
        }
        return true;
    }
}

