<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Contracts;

use Cognesy\Addons\Collaboration\Data\CollaborationState;

interface CanProcessCollaborationState
{
    /**
     * @param callable(CollaborationState): CollaborationState|null $next
     */
    public function process(CollaborationState $state, ?callable $next = null) : CollaborationState;
}
