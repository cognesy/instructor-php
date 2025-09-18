<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Contracts\Internal;

use Cognesy\Addons\Core\Contracts\CanDecideToContinue;

interface CanProvideContinuationCriteria
{
    /**
     * Get the next continuation criteria based on the current state.
     *
     * @param object $state The current state of the process (e.g., ChatState, ToolUseState).
     * @return iterable<CanDecideToContinue> An iterable of continuation criteria relevant to the given state.
     */
    public function nextContinuationCriterionFor(object $state): iterable;
}