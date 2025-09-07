<?php declare(strict_types=1);

namespace Cognesy\Http\Exceptions;

use Cognesy\Http\Data\HttpRequest;
use Throwable;

class ConnectionException extends NetworkException
{
    public function __construct(
        string $message,
        ?HttpRequest $request = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $request, null, null, $previous);
    }
}