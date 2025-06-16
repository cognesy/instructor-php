<?php

namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;

class PendingHttpResponse
{
    private readonly CanHandleHttpRequest $handler;
    private readonly HttpRequest $request;

    private ?HttpResponse $response = null;

    public function __construct(
        HttpRequest          $request,
        CanHandleHttpRequest $handler,
    ) {
        $this->request = $request;
        $this->handler = $handler;
    }

    public function get(): HttpResponse {
        return $this->makeResponse($this->request);
    }

    public function statusCode(): int {
        return $this
            ->makeResponse($this->request)
            ->statusCode();
    }

    public function headers(): array {
        return $this
            ->makeResponse($this->request)
            ->headers();
    }

    public function content(): string {
        return $this
            ->makeResponse($this->request)
            ->body();
    }

    public function stream(?int $chunkSize = null): iterable {
        yield from $this
            ->makeResponse($this->request->withStreaming(true))
            ->stream($chunkSize);
    }

    private function makeResponse(HttpRequest $request): HttpResponse {
        if (empty($this->response)) {
            $this->response = $this->handler->handle($request);
        }

        return $this->response;
    }
}