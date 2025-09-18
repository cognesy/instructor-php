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
    private array $before = [];
    private array $after = [];
    private ?Closure $onError = null;
    private ?Closure $finishWhen = null;
    private ?Closure $then = null;

    private ?ProcessorChain $pipeline;

    /**
     * @param list<callable> $processors
     */
    public function __construct(array $processors = []) {
        $this->pipeline = empty($processors) ? null : new ProcessorChain($processors);
    }

    /**
     * Static constructor to kick off a chain.
     *
     * @param callable|callable[] $processors
     */
    public static function through(callable|array $processors): self {
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
            foreach ($this->pipeline->processors() as $processor) {
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

    public function beforeEach(callable $callback): static {
        $this->before[] = $callback;
        return $this;
    }

    public function afterEach(callable $callback): static {
        $this->after[] = $callback;
        return $this;
    }

    public function onError(callable $callback): static {
        $this->onError = $callback;
        return $this;
    }

    public function finishWhen(callable $callback): static {
        $this->finishWhen = $callback;
        return $this;
    }

    public function then(callable $callback): static {
        $this->then = $callback;
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