<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\EventSource;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\Base\BaseMiddleware;
use Cognesy\Http\Middleware\EventSource\Listeners\CanListenToHttpEvents;

/**
 * DebugMiddleware
 *
 * A middleware that provides debugging capabilities for HTTP requests and responses.
 * It can be used with any HTTP driver that implements the CanHandleHttp interface.
 */
class EventSourceMiddleware extends BaseMiddleware
{
    /** @var CanListenToHttpEvents[] */
    private array $listeners = [];
    /** @var callable(string): (string|bool)|null */
    private $parser = null;

    public function __construct(
        protected bool $enabled = true,
    ) {}

    public function withListeners(CanListenToHttpEvents ...$listeners): self {
        foreach ($listeners as $listener) {
            $this->listeners[] = match (true) {
                $listener instanceof CanListenToHttpEvents => $listener,
                default => throw new \InvalidArgumentException('Handler must implement CanHandleHttpEvents interface.')
            };
        }
        return $this;
    }

    /**
     * @param callable(string): (string|bool) $parser
     */
    public function withParser(callable $parser): self {
        $this->parser = $parser;
        return $this;
    }

    #[\Override]
    protected function shouldExecute(HttpRequest $request): bool {
        return $this->enabled;
    }

    #[\Override]
    protected function beforeRequest(HttpRequest $request): HttpRequest {
        foreach ($this->listeners as $handler) {
            $handler->onRequestReceived($request);
        }
        return $request;
    }

    #[\Override]
    protected function afterRequest(HttpRequest $request, HttpResponse $response): HttpResponse {
        foreach ($this->listeners as $handler) {
            $handler->onResponseReceived($request, $response);
        }
        return $response;
    }

    #[\Override]
    protected function shouldDecorateResponse(HttpRequest $request, HttpResponse $response,): bool {
        return $request->isStreamed();
    }

    #[\Override]
    protected function toResponse(HttpRequest $request, HttpResponse $response): HttpResponse {
        return EventSourceResponseDecorator::decorate(
            request: $request,
            response: $response,
            listeners: $this->listeners,
            parser: $this->parser,
        );
    }
}
