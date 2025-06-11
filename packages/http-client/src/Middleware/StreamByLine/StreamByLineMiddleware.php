<?php
namespace Cognesy\Http\Middleware\StreamByLine;

use Closure;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handles processing of streaming responses by converting raw chunks into properly processed data lines.
 */
class StreamByLineMiddleware extends BaseMiddleware
{
    protected Closure $parser;
    protected EventDispatcherInterface $events;

    public function __construct(
        ?callable $parser = null,
        ?EventDispatcherInterface $events = null
    )
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