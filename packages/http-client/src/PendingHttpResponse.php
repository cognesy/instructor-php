<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;

class PendingHttpResponse
{
    private readonly CanHandleHttpRequest $driver;
    private readonly HttpRequest $request;

    private ?HttpResponse $response = null;

    public function __construct(
        HttpRequest          $request,
        CanHandleHttpRequest $driver,
    ) {
        $this->request = $request;
        $this->driver = $driver;
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

    // INTERNAL ////////////////////////////////////////////////////////////////////////

    private function makeResponse(HttpRequest $request): HttpResponse {
        if (empty($this->response)) {
            $this->response = $this->driver->handle($request);
        }

        return $this->response;
    }
}