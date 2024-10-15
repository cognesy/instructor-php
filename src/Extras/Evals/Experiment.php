<?php
namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Extras\Evals\Console\Display;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanBeExecuted;
use Exception;
use Generator;

class Experiment {
    private Display $display;

    private Generator $cases;
    private CanBeExecuted $executor;
    /** @var Execution[] */
    private array $executions = [];
    /** @var CanEvaluateExecution[] */
    private array $evaluators;

    /** @var Exception[] */
    private array $exceptions = [];

    public function __construct(
        Generator                  $cases,
        CanBeExecuted              $executor,
        array|CanEvaluateExecution $evaluators,
    ) {
        $this->display = new Display();

        $this->cases = $cases;
        $this->executor = $executor;
        $this->evaluators = match (true) {
            is_array($evaluators) => $evaluators,
            default => [$evaluators],
        };
    }

    // PUBLIC //////////////////////////////////////////////////

    /**
     * @return array<Execution>
     */
    public function execute() : array {
        foreach ($this->cases as $case) {
            $execution = (new Execution(
                    label: (string) $case,
                    connection: $case->connection,
                    mode: $case->mode,
                    isStreamed: $case->isStreaming,
                ))
                ->withExecutor($this->executor)
                ->withEvaluators($this->evaluators);

            $this->display->before($execution);
            try {
                $execution->execute();
            } catch(Exception $e) {
                $this->exceptions[$execution->id] = $execution->exception;
            }
            $this->executed[] = $execution;
            $this->display->after($execution);
        }

        if (!empty($this->exceptions)) {
            $this->display->displayExceptions($this->exceptions);
        }
        return $this->executions;
    }
}
