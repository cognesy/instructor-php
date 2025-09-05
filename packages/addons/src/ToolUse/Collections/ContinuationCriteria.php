<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Collections;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseState;

final class ContinuationCriteria
{
    /** @var CanDecideToContinue[] */
    private array $items = [];

    public function add(CanDecideToContinue ...$criteria) : self {
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

