<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Selectors\RoundRobin;

use Cognesy\Addons\Collaboration\Collections\Collaborators;
use Cognesy\Addons\Collaboration\Contracts\CanChooseNextCollaborator;
use Cognesy\Addons\Collaboration\Contracts\CanCollaborate;
use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Exceptions\NoCollaboratorException;

final class RoundRobinSelector implements CanChooseNextCollaborator
{
    private int $index = 0;

    #[\Override]
    public function nextCollaborator(CollaborationState $state, Collaborators $collaborators) : CanCollaborate {
        if ($collaborators->count() === 0) {
            throw new NoCollaboratorException('No participants available to select from.');
        }
        $participant = $collaborators->at($this->index);
        if ($participant === null) {
            throw new NoCollaboratorException('No participant found at index ' . $this->index);
        }
        $this->index = ($this->index + 1) % $collaborators->count();
        return $participant;
    }
}
