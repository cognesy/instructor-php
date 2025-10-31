<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Contracts;

use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Messages\Messages;

interface CanRespondWithMessages
{
    public function respond(CollaborationState $state): Messages;
}