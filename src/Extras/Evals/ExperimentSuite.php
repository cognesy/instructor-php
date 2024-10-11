<?php
namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Extras\Evals\Console\Display;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Data\InferenceParamsCase;
use Exception;
use Generator;

class ExperimentSuite {
    private Display $display;

    private CanExecuteExperiment $executor;
    private CanEvaluateExperiment $evaluator;
    private Generator $cases;

    private array $exceptions = [];
    private array $experiments = [];

    public function __construct(
        CanExecuteExperiment  $executor,
        CanEvaluateExperiment $evaluator,
        Generator $cases,
    ) {
        $this->display = new Display();

        $this->executor = $executor;
        $this->evaluator = $evaluator;
        $this->cases = $cases;
    }

    // PUBLIC //////////////////////////////////////////////////

    /**
     * @param Generator<InferenceParamsCase> $cases
     * @return array<Experiment>
     */
    public function execute() : array {
        foreach ($this->cases as $case) {
            $experiment = (new Experiment(
                    id: (string) $case,
                    connection: $case->connection,
                    mode: $case->mode,
                    isStreamed: $case->isStreaming,
                ))
                ->withExecutor($this->executor)
                ->withEvaluator($this->evaluator);

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
