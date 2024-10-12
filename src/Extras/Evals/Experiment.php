<?php

namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanAggregateValues;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use Exception;

class Experiment
{
    public CanExecuteExperiment $executor;
    public CanAggregateValues $aggregator;
    public array $evaluators;

    public string $id = '';

    public string $label = '';
    public string $connection = '';
    public Mode $mode = Mode::Json;
    public bool $isStreamed = false;

    public ?LLMResponse $response = null;

    public array $evaluations = [];
    public ?Metric $aggregate = null;

    public string $notes = '';
    public ?DateTime $startedAt = null;
    public float $timeElapsed = 0.0;
    public ?Exception $exception = null;
    public Usage $usage;

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

    public function withExecutor(CanExecuteExperiment $executor) : self {
        $this->executor = $executor;
        return $this;
    }

    public function withEvaluators(array|CanEvaluateExperiment $evaluator) : self {
        $this->evaluators = match(true) {
            is_array($evaluator) => $evaluator,
            default => [$evaluator],
        };
        foreach($evaluator as $eval) {
            if (!($eval instanceof CanEvaluateExperiment)) {
                $class = get_class($eval);
                throw new Exception("Evaluator $class has to implement CanEvaluateExperiment interface");
            }
        }
        return $this;
    }

    public function withAggregator(CanAggregateValues $aggregator) : self {
        $this->aggregator = $aggregator;
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

    public function execute() : void {
        try {
            // execute and measure time + usage
            $this->startedAt = new DateTime();
            $time = microtime(true);
            $this->response = $this->executor->execute($this);
            $this->timeElapsed = microtime(true) - $time;
            $this->usage = $this->response->usage();
            $this->notes = $this->response->content();

            $this->evaluations = $this->evaluate();
            $this->aggregate = $this->aggregator->aggregate($this);
        } catch(Exception $e) {
            $this->timeElapsed = microtime(true) - $time;
            $this->notes = $e->getMessage();
            $this->exception = $e;
            throw $e;
        }
    }

    // INTERNAL /////////////////////////////////////////////////

    private function evaluate() : array {
        $evaluations = [];
        /** @var CanEvaluateExperiment $evaluator */
        foreach($this->evaluators as $evaluator) {
            $evaluations[] = $this->makeEvaluation($evaluator, $this);
        }
        return $evaluations;
    }

    private function makeEvaluation(CanEvaluateExperiment $evaluator, Experiment $experiment) : Evaluation {
        $time = microtime(true);
        $evaluation = $evaluator->evaluate($experiment);
        $evaluation->timeElapsed = microtime(true) - $time;
        return $evaluation;
    }
}
