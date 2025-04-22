<?php

namespace Cognesy\Utils\Messages\Contracts;

use Cognesy\Utils\Messages\Messages;

interface CanProvideMessages
{
    public function toMessages(): Messages;
}