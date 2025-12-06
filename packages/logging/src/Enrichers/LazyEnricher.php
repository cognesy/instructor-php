<?php

declare(strict_types=1);

namespace Cognesy\Logging\Enrichers;

use Closure;
use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventEnricher;
use Cognesy\Logging\LogContext;

/**
 * Lazy enricher that only evaluates context when needed
 */
final readonly class LazyEnricher implements EventEnricher
{
    public function __construct(
        private Closure $contextProvider,
        private string $contextKey = 'framework',
    ) {}

    public function __invoke(Event $event): LogContext
    {
        $baseContext = LogContext::fromEvent($event);
        $lazyContext = ($this->contextProvider)();

        return match ($this->contextKey) {
            'framework' => $baseContext->withFrameworkContext($lazyContext),
            'metrics' => $baseContext->withPerformanceMetrics($lazyContext),
            'user' => $baseContext->withUserContext($lazyContext),
            default => $baseContext->withFrameworkContext([$this->contextKey => $lazyContext]),
        };
    }

    public static function framework(Closure $provider): self
    {
        return new self($provider, 'framework');
    }

    public static function metrics(Closure $provider): self
    {
        return new self($provider, 'metrics');
    }

    public static function user(Closure $provider): self
    {
        return new self($provider, 'user');
    }
}