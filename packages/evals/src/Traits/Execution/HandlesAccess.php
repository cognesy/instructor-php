<?php declare(strict_types=1);

namespace Cognesy\Evals\Traits\Execution;

use Cognesy\Evals\Contracts\CanRunExecution;
use Cognesy\Evals\Observation;
use Cognesy\Evals\Observation\SelectObservations;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Data\DataMap;
use DateTime;
use Exception;

trait HandlesAccess
{
    public function id() : string {
        return $this->id;
    }

    public function get(string $key) : mixed {
        return $this->data->get($key);
    }

    public function set(string $key, mixed $value) : self {
        $this->data->set($key, $value);
        return $this;
    }

    public function data() : DataMap {
        return $this->data;
    }

    public function withData(DataMap $data) : self {
        $this->data = $data;
        return $this;
    }

    public function withExecutor(CanRunExecution $executor) : self {
        $this->action = $executor;
        return $this;
    }

    public function withProcessors(array|object $processors) : self {
        $this->processors = match(true) {
            is_array($processors) => $processors,
            default => [$processors],
        };
        return $this;
    }

    public function withPostprocessors(array $processors) : self {
        $this->postprocessors = match(true) {
            is_array($processors) => $processors,
            default => [$processors],
        };
        return $this;
    }

    /**
     * @return Observation[]
     */
    public function observations() : array {
        return $this->observations;
    }

    public function hasObservations() : bool {
        return count($this->observations) > 0;
    }

    public function exception() : ?Exception {
        return $this->exception;
    }

    public function hasException() : bool {
        return $this->exception !== null;
    }

    public function status() : string {
        return $this->exception ? 'failed' : 'success';
    }

    public function startedAt() : ?DateTime {
        return $this->startedAt;
    }

    public function timeElapsed() : float {
        return $this->timeElapsed;
    }

    public function usage() : Usage {
        return $this->usage;
    }

    public function totalTps() : float {
        if ($this->timeElapsed() === 0.0) {
            return 0.0;
        }
        return $this->usage->total() / $this->timeElapsed();
    }

    public function outputTps() : float {
        if ($this->timeElapsed === 0.0) {
            return 0.0;
        }
        return $this->usage->output() / $this->timeElapsed();
    }

    public function hasMetrics() : bool {
        return count($this->metrics()) > 0;
    }

    /**
     * @return Observation[]
     */
    public function metrics() : array {
        return SelectObservations::from($this->observations)
            ->withTypes(['metric'])
            ->all();
    }

    public function hasFeedback() : bool {
        return count($this->feedback()) > 0;
    }

    /**
     * @return Observation[]
     */
    public function feedback() : array {
        return SelectObservations::from($this->observations)
            ->withTypes(['feedback'])
            ->all();
    }

    public function hasSummaries() : bool {
        return count($this->summaries()) > 0;
    }

    /**
     * @return Observation[]
     */
    public function summaries() : array {
        return SelectObservations::from([$this->observations])
            ->withTypes(['summary'])
            ->all();
    }
}