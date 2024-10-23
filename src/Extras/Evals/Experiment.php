<?php
namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Extras\Evals\Console\Display;
use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\CanProvideExecutionObservations;
use Cognesy\Instructor\Extras\Evals\Contracts\CanRunExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanSummarizeExperiment;
use Cognesy\Instructor\Extras\Evals\Observation\MakeObservations;
use Cognesy\Instructor\Extras\Evals\Observation\SelectObservations;
use Cognesy\Instructor\Extras\Evals\Observers\ExperimentDuration;
use Cognesy\Instructor\Extras\Evals\Observers\ExperimentTotalTokens;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\DataMap;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use Exception;
use Generator;

class Experiment {
    private array $defaultProcessors = [
        ExperimentDuration::class,
        ExperimentTotalTokens::class,
    ];

    private Display $display;
    private Generator $cases;
    private CanRunExecution $executor;
    private array $processors = [];
    private array $postprocessors = [];

    readonly private string $id;
    private ?DateTime $startedAt = null;
    private float $timeElapsed = 0.0;
    private ?Usage $usage = null;
    private DataMap $data;

    /** @var Execution[] */
    private array $executions = [];
    /** @var Exception[] */
    private array $exceptions = [];

    /** @var Observation[] */
    private array $observations = [];

    public function __construct(
        Generator       $cases,
        CanRunExecution $executor,
        array|object    $processors,
        array|object    $postprocessors,
    ) {
        $this->id = Uuid::uuid4();
        $this->display = new Display();
        $this->data = new DataMap();

        $this->cases = $cases;
        $this->executor = $executor;
        $this->processors = match (true) {
            is_array($processors) => $processors,
            default => [$processors],
        };
        $this->postprocessors = match (true) {
            is_array($postprocessors) => $postprocessors,
            default => [$postprocessors],
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
     * @return Observation[]
     */
    public function execute() : array {
        $this->startedAt = new DateTime();
        $this->display->header($this);

        // execute cases
        foreach ($this->cases as $case) {
            $this->executeCase($case);
        }
        $this->usage = $this->accumulateUsage();
        $this->timeElapsed = microtime(true) - $this->startedAt->getTimestamp();

        $this->observations = $this->makeObservations();

        $this->display->footer($this);
        if (!empty($this->exceptions)) {
            $this->display->displayExceptions($this->exceptions);
        }

        return $this->summaries();
    }

    /**
     * @return Execution[]
     */
    public function executions() : array {
        return $this->executions;
    }

    /**
     * @return Observation[]
     */
    public function metrics(string $name) : array {
        return SelectObservations::from($this->observations)->withTypes(['metric'])->get($name);
    }

    /**
     * @return Observation[]
     */
    public function summaries() : array {
        return SelectObservations::from($this->observations)->withTypes(['summary'])->all();
    }

    /**
     * @return Observation[]
     */
    public function feedback() : array {
        return SelectObservations::from($this->observations)->withTypes(['feedback'])->all();
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

    /**
     * @return Observation[]
     */
    public function executionObservations() : array {
        $observations = [];
        foreach($this->executions as $execution) {
            foreach($execution->observations() as $observation) {
                $observations[] = $observation;
            }
        }
        return $observations;
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
        $caseData = match(true) {
            is_array($case) => $case,
            method_exists($case, 'toArray') => $case->toArray(),
            default => (array) $case,
        };
        return (new Execution(case: $caseData))
            ->withExecutor($this->executor)
            ->withProcessors($this->processors)
            ->withPostprocessors($this->postprocessors);
    }

    private function accumulateUsage() : Usage {
        $usage = new Usage();
        foreach ($this->executions as $execution) {
            $usage->accumulate($execution->usage());
        }
        return $usage;
    }

    private function makeObservations() : array {
        // execute observers
        $observations = MakeObservations::for($this)
            ->withSources([
                $this->processors,
                $this->defaultProcessors,
            ])
            ->only([
                CanObserveExperiment::class,
                CanObserveExecution::class,
                CanProvideExecutionObservations::class,
            ]);

        // execute summarizers
        $summaries = MakeObservations::for($this)
            ->withSources([
                $this->postprocessors,
            ])
            ->only([
                CanSummarizeExperiment::class,
                CanObserveExperiment::class,
                CanProvideExecutionObservations::class,
            ]);

        return array_filter(array_merge($observations, $summaries));
    }
}
