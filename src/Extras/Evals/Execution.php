<?php

namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanBeExecuted;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use Exception;

class Execution
{
    public CanBeExecuted $executor;
    /** @var CanEvaluateExecution[] */
    public array $evaluators;

    public string $id = '';
    public ?DateTime $startedAt = null;
    public float $timeElapsed = 0.0;
    public Usage $usage;

    public string $label = '';
    public string $notes = '';
    /** @var Evaluation[] */
    public array $evaluations = [];
    public ?Exception $exception = null;

    // this needs to be generalized
    public string $connection = '';
    public Mode $mode = Mode::Json;
    public bool $isStreamed = false;
    public ?LLMResponse $response = null;
    // this needs to be generalized

    public function __construct(
        string $label = '',
        string $connection = '',
        Mode $mode = Mode::Json,
        bool $isStreamed = false,
    ) {
        $this->id = Uuid::uuid4();
        $this->label = $label;
        $this->connection = $connection;
        $this->mode = $mode;
        $this->isStreamed = $isStreamed;
    }

    public function withExecutor(CanBeExecuted $executor) : self {
        $this->executor = $executor;
        return $this;
    }

    public function withEvaluators(array|CanEvaluateExecution $evaluator) : self {
        $this->evaluators = match(true) {
            is_array($evaluator) => $evaluator,
            default => [$evaluator],
        };
        foreach($evaluator as $eval) {
            if (!($eval instanceof CanEvaluateExecution)) {
                $class = get_class($eval);
                throw new Exception("Evaluator $class has to implement CanEvaluateExperiment interface");
            }
        }
        return $this;
    }

    public function execute() : void {
        try {
            // execute and measure time + usage
            $this->startedAt = new DateTime();
            $time = microtime(true);
            $this->executor->execute($this);
            $this->timeElapsed = microtime(true) - $time;
            $this->usage = $this->response->usage();
            $this->notes = $this->response->content();

            $this->evaluations = $this->evaluate();
        } catch(Exception $e) {
            $this->timeElapsed = microtime(true) - $time;
            $this->notes = $e->getMessage();
            $this->exception = $e;
            throw $e;
        }
    }

    public function totalTps() : float {
        if ($this->timeElapsed === 0) {
            return 0;
        }
        return ($this->usage->total()) / $this->timeElapsed;
    }

    public function outputTps() : float {
        if ($this->timeElapsed === 0) {
            return 0;
        }
        return $this->usage->output() / $this->timeElapsed;
    }

    public function hasException() : bool {
        return $this->exception !== null;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function evaluate() : array {
        $evaluations = [];
        /** @var CanEvaluateExecution $evaluator */
        foreach($this->evaluators as $evaluator) {
            $evaluations[] = $this->makeEvaluation($evaluator, $this);
        }
        return $evaluations;
    }

    private function makeEvaluation(CanEvaluateExecution $evaluator, Execution $execution) : Evaluation {
        $time = microtime(true);
        $evaluation = $evaluator->evaluate($execution);
        $evaluation->timeElapsed = microtime(true) - $time;
        return $evaluation;
    }
}
