<?php
namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Extras\Evals\Console\Display;
use Cognesy\Instructor\Extras\Evals\Contracts\CanAggregateValues;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Exception;
use Generator;

class ExperimentSuite {
    private Display $display;

    private Generator $cases;
    private CanExecuteExperiment $executor;
    /** @var CanEvaluateExperiment[] */
    private array $evaluators;
    private CanAggregateValues $aggregator;

    private array $exceptions = [];
    private array $experiments = [];

    public function __construct(
        Generator $cases,
        CanExecuteExperiment $executor,
        array|CanEvaluateExperiment $evaluators,
        CanAggregateValues $aggregator,
    ) {
        $this->display = new Display();

        $this->cases = $cases;
        $this->executor = $executor;
        $this->evaluators = match (true) {
            is_array($evaluators) => $evaluators,
            default => [$evaluators],
        };
        $this->aggregator = $aggregator;
    }

    // PUBLIC //////////////////////////////////////////////////

    /**
     * @return array<Experiment>
     */
    public function execute() : array {
        foreach ($this->cases as $case) {
            $experiment = (new Experiment(
                    label: (string) $case,
                    connection: $case->connection,
                    mode: $case->mode,
                    isStreamed: $case->isStreaming,
                ))
                ->withExecutor($this->executor)
                ->withEvaluators($this->evaluators)
                ->withAggregator($this->aggregator);

            $this->display->before($experiment);
            try {
                $experiment->execute();
            } catch(Exception $e) {
                $this->exceptions[$experiment->id] = $experiment->exception;
            }
            $this->executed[] = $experiment;
            $this->display->after($experiment);
        }

        if (!empty($this->exceptions)) {
            $this->display->displayExceptions($this->exceptions);
        }
        return $this->experiments;
    }
}
