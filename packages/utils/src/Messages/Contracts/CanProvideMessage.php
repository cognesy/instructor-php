<?php declare(strict_types=1);

namespace Cognesy\Utils\Messages\Contracts;

use Cognesy\Utils\Messages\Message;

interface CanProvideMessage
{
    public function toMessage(): Message;
}