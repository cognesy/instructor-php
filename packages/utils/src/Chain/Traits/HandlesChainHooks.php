<?php declare(strict_types=1);

namespace Cognesy\Utils\Chain\Traits;

use Closure;
use Throwable;

/**
 * Mixin for before/after hooks, error handler, finish‐when, and final "then" callback.
 */
trait HandlesChainHooks
{
    private array        $before  = [];
    private array        $after   = [];
    private ?Closure     $onError     = null;
    private ?Closure     $finishWhen  = null;
    private ?Closure     $then        = null;

    public function beforeEach(callable $callback): static
    {
        $this->before[] = $callback;
        return $this;
    }

    public function afterEach(callable $callback): static
    {
        $this->after[] = $callback;
        return $this;
    }

    public function onError(callable $callback): static
    {
        $this->onError = $callback;
        return $this;
    }

    public function finishWhen(callable $callback): static
    {
        $this->finishWhen = $callback;
        return $this;
    }

    public function then(callable $callback): static
    {
        $this->then = $callback;
        return $this;
    }

    /** @internal */
    protected function runBefore(mixed $payload): mixed
    {
        foreach ($this->before as $callback) {
            $result = $callback($payload);
            if ($result !== null) {
                $payload = $result;
            }
        }
        return $payload;
    }

    /** @internal */
    protected function runAfter(mixed $payload): mixed
    {
        foreach ($this->after as $callback) {
            $result = $callback($payload);
            if ($result !== null) {
                $payload = $result;
            }
        }
        return $payload;
    }

    /** @internal */
    protected function shouldFinish(mixed $payload): bool
    {
        return $this->finishWhen
            ? (bool) ($this->finishWhen)($payload)
            : false;
    }

    /** @internal */
    protected function runThen(mixed $payload): mixed
    {
        return $this->then
            ? ($this->then)($payload)
            : $payload;
    }

    /** @internal */
    protected function handleError(Throwable $e): mixed
    {
        if ($this->onError) {
            return ($this->onError)($e);
        }
        throw $e;
    }
}