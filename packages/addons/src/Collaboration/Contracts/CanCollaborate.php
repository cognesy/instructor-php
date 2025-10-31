<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Contracts;

use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Data\CollaborationStep;

interface CanCollaborate
{
    public function name() : string;
    public function act(CollaborationState $state) : CollaborationStep;
}

