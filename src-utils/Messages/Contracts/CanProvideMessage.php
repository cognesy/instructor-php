<?php

namespace Cognesy\Utils\Messages\Contracts;

use Cognesy\Utils\Messages\Message;

interface CanProvideMessage
{
    public function toMessage(): Message;
}