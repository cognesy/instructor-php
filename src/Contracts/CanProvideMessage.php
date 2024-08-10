<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\Messages\Message;

interface CanProvideMessage
{
    public function toMessage(): Message;
}