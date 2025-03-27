<?php
namespace Cognesy\Polyglot\Http\Middleware\StreamByLine;

use Closure;
use Cognesy\Polyglot\Http\BaseMiddleware;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Utils\Events\EventDispatcher;

/**
 * Handles processing of streaming responses by converting raw chunks into properly processed data lines.
 */
class StreamByLineMiddleware extends BaseMiddleware
{
    protected Closure $parser;
    protected EventDispatcher $events;

    public function __construct(?callable $parser = null, ?EventDispatcher $events = null)
    {
        $this->parser = Closure::fromCallable($parser ?? fn($line) => $line);
        $this->events = $events ?? new EventDispatcher();
    }

    protected function shouldDecorateResponse(HttpClientRequest $request, HttpClientResponse $response): bool
    {
        return $request->isStreamed();
    }

    protected function toResponse(HttpClientRequest $request, HttpClientResponse $response): HttpClientResponse
    {
        return new StreamByLineResponseDecorator($request, $response, $this->parser, $this->events);
    }
}