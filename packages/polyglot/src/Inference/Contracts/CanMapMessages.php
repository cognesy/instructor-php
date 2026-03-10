<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Messages\Messages;

interface CanMapMessages
{
    public function map(Messages $messages): array;
}
