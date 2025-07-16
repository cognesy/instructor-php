<?php declare(strict_types=1);

namespace Cognesy\Http\Exceptions;

use Cognesy\Http\Data\HttpRequest;
use Exception;
use Throwable;

class HttpRequestException extends Exception
{
    private ?HttpRequest $request;

    public function __construct(
        string $message,
        ?HttpRequest $request = null,
        ?Throwable $previous = null,
    ) {
        $this->request = $request;
        $message = match(true) {
            $request !== null => sprintf(
                'HTTP Request Exception: %s. Request: %s %s. Headers: %s. Body: %s',
                $message,
                $request->method(),
                $request->url(),
                json_encode($request->headers()),
                json_encode($request->body()->toArray()),
            ),
            default => sprintf('HTTP Request Exception: %s', $message),
        };
        parent::__construct($message, 0, $previous);
    }

    public function getRequest(): HttpRequest {
        return $this->request;
    }
}
