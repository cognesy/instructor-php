<?php declare(strict_types=1);

namespace Cognesy\Http\Exceptions;

use Cognesy\Http\Data\HttpRequest;
use Throwable;

class NetworkException extends HttpRequestException
{
    #[\Override]
    public function isRetriable(): bool
    {
        return true;
    }
}