<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Utils\Messages\Message;

interface CanProvideMessage
{
    public function toMessage(): Message;
}