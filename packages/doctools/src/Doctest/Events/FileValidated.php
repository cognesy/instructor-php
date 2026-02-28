<?php declare(strict_types=1);

namespace Cognesy\Doctools\Doctest\Events;

use Cognesy\Doctools\Doctest\Data\ValidationResult;
use Cognesy\Events\Event;

class FileValidated extends Event
{
    public function __construct(
        public readonly ValidationResult $result,
    ) {}
}