<?php

namespace Cognesy\Instructor\Contracts\CanProvideMessage;

use Cognesy\Instructor\Data\Messages\Message;

interface CanProvideMessage
{
    public function toMessage(): Message;
}