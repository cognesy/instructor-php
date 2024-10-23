<?php

namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanProvideExecutionObservations;
use Cognesy\Instructor\Extras\Evals\Contracts\CanRunExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanSummarizeExecution;
use Cognesy\Instructor\Extras\Evals\Observation\MakeObservations;
use Cognesy\Instructor\Extras\Evals\Observation\SelectObservations;
use Cognesy\Instructor\Extras\Evals\Observers\ExecutionDuration;
use Cognesy\Instructor\Extras\Evals\Observers\ExecutionTotalTokens;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\DataMap;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use Exception;

class Execution
{
    /** @var CanObserveExecution[] */
    private array $defaultObservers = [
        ExecutionDuration::class,
        ExecutionTotalTokens::class,
    ];

    private CanRunExecution $action;
    private array $processors = [];
    private array $postprocessors = [];

    private string $id;
    private ?DateTime $startedAt = null;
    private float $timeElapsed = 0.0;
    private Usage $usage;
    private DataMap $data;

    private ?Exception $exception = null;

    /** @var Observation[] */
    private array $observations = [];

    public function __construct(
        array $case,
    ) {
        $this->id = Uuid::uuid4();
        $this->data = new DataMap();
        $this->data->set('case', $case);
        $this->usage = Usage::none();
    }

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

    public function execute() : void {
        $this->startedAt = new DateTime();
        $time = microtime(true);
        try {
            $this->action->run($this);
        } catch(Exception $e) {
            $this->timeElapsed = microtime(true) - $time;
            $this->data()->set('output.notes', $e->getMessage());
            $this->exception = $e;
            throw $e;
        }
        $this->timeElapsed = microtime(true) - $time;
        $this->data()->set('output.notes', $this->get('response')?->content());
        $this->usage = $this->get('response')?->usage() ?? Usage::none();
        $this->observations = $this->makeObservations();
    }

    // HELPERS //////////////////////////////////////////////////

    /**
     * @return Observation[]
     */
    public function observations() : array {
        return $this->observations;
    }

    public function hasObservations() : bool {
        return count($this->observations) > 0;
    }

    // HELPERS //////////////////////////////////////////////////

    public function exception() : ?Exception {
        return $this->exception;
    }

    public function hasException() : bool {
        return $this->exception !== null;
    }

    public function status() : string {
        return $this->exception ? 'failed' : 'success';
    }

    public function startedAt() : DateTime {
        return $this->startedAt;
    }

    public function timeElapsed() : float {
        return $this->timeElapsed;
    }

    public function usage() : Usage {
        return $this->usage;
    }

    public function totalTps() : float {
        if ($this->timeElapsed() === 0) {
            return 0;
        }
        return $this->usage->total() / $this->timeElapsed();
    }

    public function outputTps() : float {
        if ($this->timeElapsed === 0) {
            return 0;
        }
        return $this->usage->output() / $this->timeElapsed();
    }

    /**
     * @return Observation[]
     */
    public function metrics() : array {
        return SelectObservations::from($this->observations)
            ->withTypes(['metric'])
            ->all();
    }

    public function hasMetrics() : bool {
        return count($this->metrics()) > 0;
    }

    /**
     * @return Observation[]
     */
    public function feedback() : array {
        return SelectObservations::from($this->observations)
            ->withTypes(['feedback'])
            ->all();
    }

    public function hasFeedback() : bool {
        return count($this->feedback()) > 0;
    }

    /**
     * @return Observation[]
     */
    public function summaries() : array {
        return SelectObservations::from([$this->observations])
            ->withTypes(['summary'])
            ->all();
    }

    public function hasSummaries() : bool {
        return count($this->summaries()) > 0;
    }

    private function makeObservations() : array {
        $observations = MakeObservations::for($this)
            ->withSources([
                $this->processors,
                $this->defaultObservers,
            ])
            ->only([
                CanObserveExecution::class,
                CanProvideExecutionObservations::class,
            ]);

        $summaries = MakeObservations::for($this)
            ->withSources([
                $this->postprocessors
            ])
            ->only([
                CanSummarizeExecution::class,
                CanProvideExecutionObservations::class,
            ]);

        return array_filter(array_merge($observations, $summaries));
    }
}
