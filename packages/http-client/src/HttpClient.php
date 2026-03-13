<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Extras\Middleware\EventSource\EventSourceMiddleware;
use Cognesy\Http\Middleware\MiddlewareStack;

final class HttpClient implements CanSendHttpRequests
{
    public function __construct(
        private readonly HttpClientRuntime $runtime,
    ) {}

    public static function default(): self
    {
        return HttpClientRuntime::fromConfig()->client();
    }

    public static function using(string $preset, ?string $basePath = null): self
    {
        return self::fromConfig(HttpClientConfig::fromPreset($preset, $basePath));
    }

    public static function fromConfig(HttpClientConfig $config): self
    {
        return HttpClientRuntime::fromConfig(config: $config)->client();
    }

    public static function fromDriver(CanHandleHttpRequest $driver): self
    {
        return HttpClientRuntime::fromConfig(driver: $driver)->client();
    }

    #[\Override]
    public function send(HttpRequest $request): PendingHttpResponse
    {
        return $this->runtime->send($request);
    }

    public function withMiddlewareStack(MiddlewareStack $middlewareStack): self
    {
        return new self($this->runtime->withMiddlewareStack($middlewareStack));
    }

    public function withMiddleware(HttpMiddleware $middleware, ?string $name = null): self
    {
        return new self($this->runtime->withMiddleware($middleware, $name));
    }

    public function withoutMiddleware(string $name): self
    {
        return new self($this->runtime->withoutMiddleware($name));
    }

    /**
     * @deprecated Use withMiddleware((new EventSourceMiddleware())->withParser(...)) for explicit SSE behavior.
     */
    public function withSSEStream(): self
    {
        $middleware = (new EventSourceMiddleware(true))
            ->withParser(static fn(string $payload): string => $payload);

        return $this->withMiddleware($middleware);
    }

    public function runtime(): HttpClientRuntime
    {
        return $this->runtime;
    }

    public function config(): HttpClientConfig
    {
        return $this->runtime->config();
    }
}
