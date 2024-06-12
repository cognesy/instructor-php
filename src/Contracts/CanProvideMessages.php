<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\Messages\Messages;

interface CanProvideMessages
{
    public function toMessages(): Messages;
}