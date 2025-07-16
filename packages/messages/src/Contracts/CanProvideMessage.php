<?php declare(strict_types=1);

namespace Cognesy\Messages\Contracts;

use Cognesy\Messages\Message;

interface CanProvideMessage
{
    public function toMessage(): Message;
}