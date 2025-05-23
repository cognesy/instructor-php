<?php
namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Debug\Debug;
use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Http\Middleware\BufferResponse\BufferResponseMiddleware;
use Cognesy\Http\Middleware\Debug\DebugMiddleware;
use Cognesy\Utils\Events\EventDispatcher;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The HttpClient class is a wrapper around an HTTP client driver (class implementing
 * CanHandleHttpRequest).
 *
 * It enriches any underlying HTTP client or mechanism with:
 *  - a middleware stack for processing HTTP requests
 *  - unified support for extra capabilities like debugging or buffering
 *
 * @property EventDispatcherInterface $events  Instance for dispatching events.
 * @property CanHandleHttpRequest $driver Instance that handles HTTP requests.
 * @property MiddlewareStack $stack Stack of middleware for processing requests and responses.
 */
class HttpClient implements CanHandleHttpRequest
{
    protected EventDispatcherInterface $events;
    protected CanHandleHttpRequest $driver;
    protected MiddlewareStack $stack;

    /**
     * Constructor method for initializing the HTTP client.
     *
     * @param string $client The client configuration name to load.
     * @param EventDispatcher|null $events The event dispatcher instance to use.
     * @return void
     */
    public function __construct(
        CanHandleHttpRequest $driver = null,
        EventDispatcherInterface $events = null,
        MiddlewareStack $stack = null,
    ) {
        $this->events = $events;
        $this->driver = $driver;
        $this->stack = $stack;
    }

    /**
     * Returns the middleware stack associated with the current HTTP client.
     * Allows to manipulate the middleware stack directly.
     */
    public function middleware(): MiddlewareStack {
        return $this->stack;
    }

    /**
     * Adds middleware to the stack.
     *
     * @param HttpMiddleware ...$middleware The middleware to add to the stack.
     * @return self Returns the instance of the class for method chaining.
     * @throws InvalidArgumentException If the specified client type is not supported.
     */
    public function withMiddleware(HttpMiddleware ...$middleware): self {
        $this->stack->appendMany($middleware);
        return $this;
    }

    public function withDebug(bool $debug = true) : self {
        if ($debug) {
            // load debug config
            $config = DebugConfig::load();
            // force http enabled
            $config->httpEnabled = true;
            $this->stack->prepend(new BufferResponseMiddleware(), 'internal:buffering');
            $this->stack->prepend(new DebugMiddleware(new Debug($config), $this->events), 'internal:debug');
        } else {
            // remove debug middleware
            $this->stack->remove('internal:debug');
            $this->stack->remove('internal:buffering');
        }
        return $this;
    }

    /**
     * Handles the HTTP request using the current HTTP driver
     * via a stack of middleware to process the request and response.
     *
     * @param HttpClientRequest $request The request to be processed.
     * @return HttpClientResponse The response indicating the access result after processing the request.
     */
    public function handle(HttpClientRequest $request): HttpClientResponse {
        return $this->stack->decorate($this->driver)->handle($request);
    }
}
