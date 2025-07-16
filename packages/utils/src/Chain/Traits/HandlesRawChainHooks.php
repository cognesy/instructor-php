<?php declare(strict_types=1);

namespace Cognesy\Utils\Chain\Traits;

trait HandlesRawChainHooks
{
    /**
     * Add a callback to be executed before each processor.
     *
     * @param callable $callback The callback to be added.
     * @return self
     */
    public function beforeEach(callable $callback) : self {
        $this->beforeCalls[] = $callback;
        return $this;
    }

    /**
     * Add a callback to be executed after each processor.
     *
     * @param callable $callback The callback to be added.
     * @return self
     */
    public function afterEach(callable $callback) : self {
        $this->afterCalls[] = $callback;
        return $this;
    }

    /**
     * Add a callback to be executed after each processor.
     *
     * @param callable $callback The callback to be added.
     * @return self
     */
    public function onError(callable $callback) : self {
        $this->onErrorCall = $callback;
        return $this;
    }

    /**
     * Add a callback to be executed to determine if processing is done.
     *
     * @param callable $callback The callback to be used.
     * @return self
     */
    public function finishWhen(callable $callback) : self {
        $this->isDoneCall[] = $callback;
        return $this;
    }

    /**
     * Set the callback to be executed after all processors have been run.
     *
     * @param callable $callback The final callback.
     * @return self
     */
    public function then(callable $callback) : self {
        $this->thenCall = $callback;
        return $this;
    }
}