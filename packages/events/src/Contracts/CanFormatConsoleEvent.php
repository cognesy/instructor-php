<?php declare(strict_types=1);

namespace Cognesy\Events\Contracts;

use Cognesy\Events\Data\ConsoleEventLine;

interface CanFormatConsoleEvent
{
    public function format(object $event): ?ConsoleEventLine;
}
