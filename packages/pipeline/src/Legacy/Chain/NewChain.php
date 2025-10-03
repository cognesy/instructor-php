<?php declare(strict_types=1);

namespace Cognesy\Utils;

namespace Cognesy\Pipeline\Legacy\Chain;

use Closure;
use Throwable;

/**
 * A lean, hookable chain of processors.
 */
final class NewChain
{
    /** @var list<callable(mixed):mixed> */
    private array $before = [];
    /** @var list<callable(mixed):mixed> */
    private array $after = [];
    /** @var (Closure(Throwable):mixed)|null */
    private ?Closure $onError = null;
    /** @var (Closure(mixed):bool)|null */
    private ?Closure $finishWhen = null;
    /** @var (Closure(mixed):mixed)|null */
    private ?Closure $then = null;

    private ?ProcessorChain $pipeline;

    /**
     * @param list<callable(mixed):mixed> $processors
     */
    public function __construct(array $processors = []) {
        $this->pipeline = empty($processors) ? null : new ProcessorChain($processors);
    }

    /**
     * Static constructor to kick off a chain.
     *
     * @param callable(mixed):mixed|list<callable(mixed):mixed> $processors
     * @return self
     */
    public static function through(callable|array $processors): self {
        /** @var list<callable(mixed):mixed> $list */
        $list = is_array($processors)
            ? $processors
            : [$processors];

        return new self($list);
    }

    /**
     * Apply the chain to a payload.
     *
     * @param mixed $payload
     * @return mixed|null
     */
    public function process(mixed $payload): mixed {
        try {
            $carry = $payload;
            foreach ($this->pipeline?->processors() ?? [] as $processor) {
                $carry = $this->runBefore($carry);
                $carry = $processor($carry);
                if ($carry === null) {
                    break;
                }
                $carry = $this->runAfter($carry);
                if ($this->shouldFinish($carry)) {
                    break;
                }
            }

            return $this->runThen($carry);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * @param callable(mixed):mixed $callback
     */
    public function beforeEach(callable $callback): static {
        $this->before[] = $callback;
        return $this;
    }

    /**
     * @param callable(mixed):mixed $callback
     */
    public function afterEach(callable $callback): static {
        $this->after[] = $callback;
        return $this;
    }

    /**
     * @param callable(Throwable):mixed $callback
     */
    public function onError(callable $callback): static {
        $this->onError = $callback(...);
        return $this;
    }

    /**
     * @param callable(mixed):bool $callback
     */
    public function finishWhen(callable $callback): static {
        $this->finishWhen = $callback(...);
        return $this;
    }

    /**
     * @param callable(mixed):mixed $callback
     */
    public function then(callable $callback): static {
        $this->then = $callback(...);
        return $this;
    }

    /** @internal */
    protected function runBefore(mixed $payload): mixed {
        foreach ($this->before as $callback) {
            $result = $callback($payload);
            if ($result !== null) {
                $payload = $result;
            }
        }
        return $payload;
    }

    /** @internal */
    protected function runAfter(mixed $payload): mixed {
        foreach ($this->after as $callback) {
            $result = $callback($payload);
            if ($result !== null) {
                $payload = $result;
            }
        }
        return $payload;
    }

    /** @internal */
    protected function shouldFinish(mixed $payload): bool {
        return $this->finishWhen
            ? (bool)($this->finishWhen)($payload)
            : false;
    }

    /** @internal */
    protected function runThen(mixed $payload): mixed {
        return $this->then
            ? ($this->then)($payload)
            : $payload;
    }

    /** @internal */
    protected function handleError(Throwable $e): mixed {
        if ($this->onError) {
            return ($this->onError)($e);
        }
        throw $e;
    }
}