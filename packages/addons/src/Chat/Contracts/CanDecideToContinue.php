<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;

interface CanDecideToContinue
{
    public function canContinue(ChatState $state) : bool;
}

