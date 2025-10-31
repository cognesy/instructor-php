<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Contracts;

use Cognesy\Addons\Collaboration\Collections\Collaborators;
use Cognesy\Addons\Collaboration\Data\CollaborationState;

interface CanChooseNextCollaborator
{
    public function nextCollaborator(CollaborationState $state, Collaborators $collaborators) : CanCollaborate;
}
