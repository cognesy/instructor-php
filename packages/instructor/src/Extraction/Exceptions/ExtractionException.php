<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Exceptions;

use RuntimeException;
use Throwable;

final class ExtractionException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
