<?php

namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Generator;

class PendingHttpResponse
{
    private readonly CanHandleHttpRequest $handler;
    private readonly HttpClientRequest $request;

    private ?HttpClientResponse $response = null;

    public function __construct(
        HttpClientRequest $request,
        CanHandleHttpRequest $handler,
    ) {
        $this->request = $request;
        $this->handler = $handler;
    }

    public function get(): HttpClientResponse {
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

    public function stream(int $chunkSize = 1): Generator {
        yield from $this
            ->makeResponse($this->request->withStreaming(true))
            ->stream($chunkSize);
    }

    private function makeResponse(HttpClientRequest $request): HttpClientResponse {
        if (empty($this->response)) {
            $this->response = $this->handler->handle($request);
        }
        return $this->response;
    }
}