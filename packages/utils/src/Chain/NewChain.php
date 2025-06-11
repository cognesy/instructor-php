<?php

namespace Cognesy\Utils;

namespace Cognesy\Utils\Chain;

use Cognesy\Utils\Chain\Traits\HandlesChainHooks;
use Throwable;

/**
 * A lean, hookable chain of processors.
 */
final class RawChain
{
    use HandlesChainHooks;

    private ?ProcessorChain $pipeline;

    /**
     * @param list<callable> $processors
     */
    public function __construct(array $processors = [])
    {
        $this->pipeline = empty($processors) ? null : new ProcessorChain($processors);
    }

    /**
     * Static constructor to kick off a chain.
     *
     * @param callable|callable[] $processors
     */
    public static function through(callable|array $processors): self
    {
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
    public function process(mixed $payload): mixed
    {
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
}