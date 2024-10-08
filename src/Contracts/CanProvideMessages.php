<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Utils\Messages\Messages;

interface CanProvideMessages
{
    public function toMessages(): Messages;
}