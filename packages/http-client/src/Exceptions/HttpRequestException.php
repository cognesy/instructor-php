<?php

namespace Cognesy\Http\Exceptions;

use Cognesy\Http\Data\HttpClientRequest;
use Exception;
use Throwable;

class HttpRequestException extends Exception {
    private HttpClientRequest $request;
    private ?Throwable $originalException = null;

    public function __construct(
        string $message,
        HttpClientRequest $request,
        Throwable $previous = null,
    ) {
        $this->request = $request;
        $message = sprintf(
            'HTTP Request Exception: %s. Request: %s %s. Headers: %s. Body: %s',
            $message,
            $request->method(),
            $request->url(),
            json_encode($request->headers()),
            json_encode($request->body()->toArray())
        );
        parent::__construct($message, 0, $previous);
    }

    public function getRequest() : HttpClientRequest {
        return $this->request;
    }
}
