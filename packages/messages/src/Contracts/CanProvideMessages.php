<?php declare(strict_types=1);

namespace Cognesy\Messages\Contracts;

use Cognesy\Messages\Messages;

interface CanProvideMessages
{
    public function toMessages(): Messages;
}