<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Logging\EventLog;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanProvideHttpDrivers;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Creation\BundledHttpDrivers;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\MiddlewareStack;

final class HttpClientRuntime
{
    private readonly CanHandleHttpRequest $handler;

    public function __construct(
        private readonly CanHandleHttpRequest $driver,
        private readonly MiddlewareStack $middlewareStack,
        private readonly CanHandleEvents $events,
        private readonly HttpClientConfig $config,
    ) {
        $this->handler = $this->middlewareStack->decorate($this->driver);
    }

    public static function fromConfig(
        ?HttpClientConfig $config = null,
        ?CanHandleEvents $events = null,
        ?CanHandleHttpRequest $driver = null,
        ?CanProvideHttpDrivers $drivers = null,
        ?object $clientInstance = null,
        ?MiddlewareStack $middlewareStack = null,
    ): self {
        $events = $events ?? EventLog::root('http.client.runtime');
        $config = $config ?? new HttpClientConfig();
        $stack = $middlewareStack ?? new MiddlewareStack($events);
        $resolvedDriver = $driver ?? self::resolveDrivers($drivers)->makeDriver(
            name: $config->driver,
            config: $config,
            events: $events,
            clientInstance: $clientInstance,
        );

        return new self(
            driver: $resolvedDriver,
            middlewareStack: $stack,
            events: $events,
            config: $config,
        );
    }

    public function client(): HttpClient
    {
        return new HttpClient($this);
    }

    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse(
            request: $request,
            driver: $this->handler,
        );
    }

    public function withMiddlewareStack(MiddlewareStack $middlewareStack): self
    {
        return new self(
            driver: $this->driver,
            middlewareStack: $middlewareStack,
            events: $this->events,
            config: $this->config,
        );
    }

    public function withMiddleware(HttpMiddleware $middleware, ?string $name = null): self
    {
        return $this->withMiddlewareStack($this->middlewareStack->append($middleware, $name));
    }

    public function withoutMiddleware(string $name): self
    {
        return $this->withMiddlewareStack($this->middlewareStack->remove($name));
    }

    public function driver(): CanHandleHttpRequest
    {
        return $this->driver;
    }

    public function middlewareStack(): MiddlewareStack
    {
        return $this->middlewareStack;
    }

    public function events(): CanHandleEvents
    {
        return $this->events;
    }

    public function config(): HttpClientConfig
    {
        return $this->config;
    }

    private static function resolveDrivers(?CanProvideHttpDrivers $drivers): CanProvideHttpDrivers
    {
        return $drivers ?? BundledHttpDrivers::registry();
    }
}
