<?php

namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanRunExecution;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\DataMap;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use Exception;

class Execution
{
    private CanRunExecution $executor;
    /** @var CanEvaluateExecution[] */
    private array $evaluators;

    private string $id;
    private ?DateTime $startedAt = null;
    private float $timeElapsed = 0.0;
    private Usage $usage;
    private DataMap $data;

    /** @var Evaluation[] */
    private array $evaluations = [];
    private ?Exception $exception = null;

    public function __construct(
        array $case,
    ) {
        $this->id = Uuid::uuid4();
        $this->data = new DataMap();
        $this->data->set('case', $case);
    }

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

    public function evaluations() : array {
        return $this->evaluations;
    }

    public function hasEvaluations() : bool {
        return count($this->evaluations) > 0;
    }

    public function exception() : Exception {
        return $this->exception;
    }

    public function hasException() : bool {
        return $this->exception !== null;
    }

    public function status() : string {
        return $this->exception ? 'failed' : 'success';
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
        $this->executor = $executor;
        return $this;
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
            $this->usage = $this->get('response')?->usage();
            $this->data()->set('output.notes', $this->get('response')?->content());

            $this->evaluations = $this->evaluate();
        } catch(Exception $e) {
            $this->timeElapsed = microtime(true) - $time;
            $this->data()->set('output.notes', $e->getMessage());
            $this->exception = $e;
            throw $e;
        }
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
        $evaluation->withTimeElapsed(microtime(true) - $time);
        return $evaluation;
    }
}
