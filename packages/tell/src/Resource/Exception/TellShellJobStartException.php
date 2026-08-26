<?php

declare(strict_types=1);

namespace Cognesy\Tell\Resource\Exception;

use RuntimeException;
use Throwable;

final class TellShellJobStartException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
