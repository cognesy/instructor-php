<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Messages;

interface CanCompileMessages {
    public function compile(ChatState $state): Messages;
}