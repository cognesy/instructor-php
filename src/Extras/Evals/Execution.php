<?php

namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanRunExecution;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
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
    private array $metadata = [];

    private string $label;
    private string $notes;
    /** @var Evaluation[] */
    private array $evaluations = [];
    private ?Exception $exception = null;

    // this needs to be generalized - eval context (or hyperparams?)
    public string $connection = '';
    public Mode $mode = Mode::Json;
    public bool $isStreamed = false;
    // more... - input
    //  - output
    //public DataMap $output;
    public ?LLMResponse $response = null;
    private array $data = [];
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
        //$this->output = new DataMap();
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

    public function label() : string {
        return $this->label;
    }

    public function notes() : string {
        return $this->notes;
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

    public function meta(string $key, mixed $default = null) : mixed {
        return $this->metadata[$key] ?? $default;
    }

    public function withMeta(string $key, mixed $value) : self {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function withExecutor(CanRunExecution $executor) : self {
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
