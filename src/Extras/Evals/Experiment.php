<?php
namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Extras\Evals\Console\Display;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanRunExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanAggregateExperimentMetrics;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\DataMap;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use Exception;
use Generator;

class Experiment {
    private Display $display;
    private Generator $cases;
    private CanRunExecution $executor;
    /** @var CanEvaluateExecution[] */
    private array $evaluators;
    /** @var CanAggregateExperimentMetrics[] */
    private array $aggregators;

    readonly private string $id;
    private ?DateTime $startedAt = null;
    private float $timeElapsed = 0.0;
    private ?Usage $usage = null;
    private DataMap $data;

    /** @var Execution[] */
    private array $executions = [];
    /** @var Exception[] */
    private array $exceptions = [];

    /** @var Metric[] */
    private array $results;

    public function __construct(
        Generator                           $cases,
        CanRunExecution                     $executor,
        array|CanEvaluateExecution          $evaluators,
        array|CanAggregateExperimentMetrics $aggregators,
    ) {
        $this->id = Uuid::uuid4();
        $this->display = new Display();
        $this->data = new DataMap();

        $this->cases = $cases;
        $this->executor = $executor;
        $this->evaluators = match (true) {
            is_array($evaluators) => $evaluators,
            default => [$evaluators],
        };
        $this->aggregators = match (true) {
            is_array($aggregators) => $aggregators,
            default => [$aggregators],
        };
    }

    // PUBLIC //////////////////////////////////////////////////

    public function id() : string {
        return $this->id;
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

    public function data() : DataMap {
        return $this->data;
    }

    /**
     * @return Metric
     */
    public function execute() : array {
        $this->startedAt = new DateTime();

        $this->display->header($this);
        foreach ($this->cases as $case) {
            $this->executeCase($case);
        }

        $this->usage = $this->accumulateUsage();

        $this->results = [];
        foreach ($this->aggregators as $aggregator) {
            $this->results[$aggregator->name()] = $aggregator->aggregate($this);
        }

        $this->timeElapsed = microtime(true) - $this->startedAt->getTimestamp();
        $this->display->footer($this);

        if (!empty($this->exceptions)) {
            $this->display->displayExceptions($this->exceptions);
        }
        return $this->results;
    }

    /**
     * @return Execution[]
     */
    public function executions() : array {
        return $this->executions;
    }

    /**
     * @return Evaluation[]
     */
    public function evaluations(string $name) : array {
        $evaluations = [];
        foreach($this->executions as $execution) {
            if ($execution->hasException()) {
                continue;
            }
            foreach($execution->evaluations() as $evaluation) {
                if ($evaluation->metric->name() === $name) {
                    $evaluations[] = $evaluation;
                }
            }
        }
        return $evaluations;
    }

    /**
     * @return Metric[]
     */
    public function results() : array {
        return $this->results;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function executeCase(mixed $case) : void {
        $execution = $this->makeExecution($case);
        $this->display->before($execution);
        try {
            $execution->execute();
        } catch(Exception $e) {
            $this->exceptions[$execution->id()] = $execution->exception();
        }
        $this->executions[] = $execution;
        $this->display->after($execution);
    }

    private function makeExecution(mixed $case) : Execution {
        return (new Execution(
            label: (string) $case,
            connection: $case->connection,
            mode: $case->mode,
            isStreamed: $case->isStreaming,
        ))
            ->withExecutor($this->executor)
            ->withEvaluators($this->evaluators);
    }

    private function accumulateUsage() : Usage {
        $usage = new Usage();
        foreach ($this->executions as $execution) {
            $usage->accumulate($execution->usage());
        }
        return $usage;
    }
}
