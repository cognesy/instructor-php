<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\ContinuationCriteria;

use Cognesy\Addons\Chat\Contracts\CanDecideToContinue;
use Cognesy\Addons\Chat\Data\ChatState;

final class FinishReasonCheck implements CanDecideToContinue
{
    /** @var string[] */
    private array $reasons;

    public function __construct(array $reasons = []) { $this->reasons = $reasons; }

    public function canContinue(ChatState $state): bool {
        $reason = $state->currentStep()?->finishReason();
        if ($reason === null) { return true; }
        if ($this->reasons === []) { return true; }
        return !in_array($reason, $this->reasons, true);
    }
}

