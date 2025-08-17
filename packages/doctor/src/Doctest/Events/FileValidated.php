<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Events;

use Cognesy\Doctor\Doctest\Data\ValidationResult;
use Cognesy\Events\Event;

class FileValidated extends Event
{
    public function __construct(
        public readonly ValidationResult $result,
    ) {}
}