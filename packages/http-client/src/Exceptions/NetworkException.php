<?php declare(strict_types=1);

namespace Cognesy\Http\Exceptions;

class NetworkException extends HttpRequestException
{
    #[\Override]
    public function isRetriable(): bool {
        return true;
    }
}