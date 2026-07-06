<?php declare(strict_types=1);

namespace Cognesy\Http\Creation;

use Cognesy\Http\Contracts\HttpMiddleware;

/**
 * Process-global registry of "ambient" HTTP middleware: an explicit, opt-in hook
 * for attaching middleware to HTTP clients that are built *implicitly* (i.e. when
 * no client is passed to a runtime).
 *
 * It is deliberately NOT consulted by {@see HttpClientBuilder} itself — only by
 * callers that opt in via {@see self::applyTo()} on the implicit-build path. Clients
 * a caller constructs and wires explicitly are therefore never silently wrapped.
 * This is what lets the examples record/replay switch cover the implicit-client
 * examples while leaving the explicit-client / mock examples untouched.
 *
 * Default state is empty, so library behavior is unchanged for everyone. Intended
 * for test harnesses and the `./examples/` switch — reset with {@see self::clear()}.
 */
final class HttpClientDefaults
{
    /** @var list<HttpMiddleware> */
    private static array $middleware = [];

    public static function withMiddleware(HttpMiddleware ...$middleware): void {
        self::$middleware = array_merge(self::$middleware, array_values($middleware));
    }

    /** @return list<HttpMiddleware> */
    public static function middleware(): array {
        return self::$middleware;
    }

    public static function hasMiddleware(): bool {
        return self::$middleware !== [];
    }

    public static function clear(): void {
        self::$middleware = [];
    }

    /**
     * Attach the registered ambient middleware to a builder on the implicit-build
     * path. No-op when nothing is registered, so the common case is free.
     */
    public static function applyTo(HttpClientBuilder $builder): HttpClientBuilder {
        return self::$middleware === []
            ? $builder
            : $builder->withMiddleware(...self::$middleware);
    }
}
