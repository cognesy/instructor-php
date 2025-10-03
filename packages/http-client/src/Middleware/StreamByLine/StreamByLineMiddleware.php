<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\StreamByLine;

use Closure;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handles processing of streaming responses by converting raw chunks into properly processed data lines.
 */
class StreamByLineMiddleware extends BaseMiddleware
{
    /** @var Closure(string): (bool|string) */
    protected Closure $parser;
    protected EventDispatcherInterface $events;

    /**
     * @param callable(string): (bool|string)|null $parser
     */
    public function __construct(
        ?callable $parser = null,
        ?EventDispatcherInterface $events = null
    )
    {
        $this->parser = Closure::fromCallable($parser ?? fn($line) => $line);
        $this->events = $events ?? new EventDispatcher();
    }

    #[\Override]
    protected function shouldDecorateResponse(HttpRequest $request, HttpResponse $response): bool
    {
        return $request->isStreamed();
    }

    #[\Override]
    protected function toResponse(HttpRequest $request, HttpResponse $response): HttpResponse
    {
        return new StreamByLineResponseDecorator($request, $response, $this->parser, $this->events);
    }
}