<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data\Collections;

use Cognesy\Addons\Chat\Contracts\CanDecideToContinueChat;
use Cognesy\Addons\Chat\Data\ChatState;

final class ContinuationCriteria
{
    /** @var CanDecideToContinueChat[] */
    private array $criteria;

    public function __construct(CanDecideToContinueChat ...$criteria) {
        $this->criteria = $criteria;
    }

    public function add(CanDecideToContinueChat ...$criteria): self {
        foreach ($criteria as $criterion) {
            $this->criteria[] = $criterion;
        }
        return $this;
    }

    public function isEmpty(): bool {
        return $this->criteria === [];
    }

    public function canContinue(ChatState $state): bool {
        foreach ($this->criteria as $criterion) {
            if (!$criterion->canContinue($state)) {
                return false;
            }
        }
        return true;
    }
}

