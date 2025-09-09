<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data\Collections;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinueToolUse;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

final readonly class ContinuationCriteria
{
    /** @var CanDecideToContinueToolUse[] */
    private array $criteria;

    public function __construct(CanDecideToContinueToolUse ...$criteria) {
        $this->criteria = $criteria;
    }

    public function withCriteria(CanDecideToContinueToolUse ...$criteria) : self {
        return new self(...$criteria);
    }

    public function isEmpty() : bool {
        return $this->criteria === [];
    }

    public function canContinue(ToolUseState $state) : bool {
        foreach ($this->criteria as $criterion) {
            if (!$criterion->canContinue($state)) {
                return false;
            }
        }
        return true;
    }
}

