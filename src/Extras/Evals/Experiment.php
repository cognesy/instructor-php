<?php

namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Exception;

class Experiment
{
    public CanExecuteExperiment $executor;
    public CanEvaluateExperiment $evaluator;

    public string $id = '';

    public string $connection = '';
    public Mode $mode = Mode::Json;
    public bool $isStreamed = false;

    public ?LLMResponse $response = null;

    public string $notes = '';
    public ?Metric $metric = null;
    public float $timeElapsed = 0.0;
    public ?Exception $exception = null;
    public int $inputTokens = 0;
    public int $outputTokens = 0;

    public function __construct(
        string $id = '',
        string $connection = '',
        Mode $mode = Mode::Json,
        bool $isStreamed = false,
    ) {
        $this->id = $id;
        $this->connection = $connection;
        $this->mode = $mode;
        $this->isStreamed = $isStreamed;
    }

    public function withExecutor(CanExecuteExperiment $executor) : self {
        $this->executor = $executor;
        return $this;
    }

    public function withEvaluator(CanEvaluateExperiment $evaluator) : self {
        $this->evaluator = $evaluator;
        return $this;
    }

    public function totalTps() : float {
        if ($this->timeElapsed === 0) {
            return 0;
        }
        return ($this->inputTokens + $this->outputTokens) / $this->timeElapsed;
    }

    public function outputTps() : float {
        if ($this->timeElapsed === 0) {
            return 0;
        }
        return $this->outputTokens / $this->timeElapsed;
    }

    public function execute() : void {
        try {
            // execute and measure time
            $time = microtime(true);
            $this->response = $this->executor->execute($this);
            $timeElapsed = microtime(true) - $time;
            $this->timeElapsed = $timeElapsed;
            $this->inputTokens = $this->response->usage()->inputTokens;
            $this->outputTokens = $this->response->usage()->outputTokens;

            $this->notes = $this->response->content();
            $this->metric = $this->evaluate();
        } catch(Exception $e) {
            $timeElapsed = microtime(true) - $time;
            $this->notes = '';
            $this->metric = new BooleanCorrectness(false);
            $this->timeElapsed = $timeElapsed;
            $this->exception = $e;
            throw $e;
        }
    }

    public function evaluate() : Metric {
        return $this->evaluator->evaluate($this);
    }
}
