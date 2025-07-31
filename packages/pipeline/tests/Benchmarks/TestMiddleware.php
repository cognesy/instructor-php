<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tests\Benchmarks;

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Middleware\PipelineMiddlewareInterface;

/**
 * Test middleware implementations for benchmarking
 */
class BenchTimerMiddleware implements PipelineMiddlewareInterface
{
    private static array $timings = [];

    public function handle(Computation $computation, callable $next): Computation {
        $start = hrtime(true);
        $result = $next($computation);
        $end = hrtime(true);

        self::$timings[] = ($end - $start) / 1000; // microseconds
        return $result;
    }

    public static function getTimings(): array {
        return self::$timings;
    }

    public static function reset(): void {
        self::$timings = [];
    }
}

class BenchRetryMiddleware implements PipelineMiddlewareInterface
{
    private int $maxRetries = 2;

    public function handle(Computation $computation, callable $next): Computation {
        $result = $next($computation);

        if ($result->isFailure() && $this->maxRetries > 0) {
            $this->maxRetries--;
            return $this->handle($computation, $next);
        }

        return $result;
    }
}

class BenchErrorLoggerMiddleware implements PipelineMiddlewareInterface
{
    private static array $errors = [];

    public function handle(Computation $computation, callable $next): Computation {
        $result = $next($computation);

        if ($result->isFailure()) {
            self::$errors[] = $result->exception()?->getMessage() ?? 'Unknown error';
        }

        return $result;
    }

    public static function getErrors(): array {
        return self::$errors;
    }

    public static function reset(): void {
        self::$errors = [];
    }
}

/**
 * Dummy logger for hooks testing
 */
class BenchDummyLogger
{
    private static array $logs = [];

    public static function log(string $message): void {
        self::$logs[] = $message;
    }

    public static function getLogs(): array {
        return self::$logs;
    }

    public static function reset(): void {
        self::$logs = [];
    }
}