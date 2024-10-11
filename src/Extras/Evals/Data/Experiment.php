<?php

namespace Cognesy\Instructor\Extras\Evals\Data;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Exception;

class Experiment
{
    public string $id = '';
    public string $connection = '';
    public Mode $mode = Mode::Json;
    public bool $isStreamed = false;

    public ExperimentData $data;

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
        $this->data = new ExperimentData();
    }

    public function withExperimentData(ExperimentData $data) : self {
        $this->data = $data;
        return $this;
    }

    public function withResponse(LLMResponse $response) : self {
        $this->response = $response;
        return $this;
    }

    public function withOutput(
        string     $notes = '',
        ?Metric    $metric = null,
        float      $timeElapsed = 0.0,
        ?Exception $exception = null,
        int        $inputTokens = 0,
        int        $outputTokens = 0,
    ) : self {
        $this->notes = $notes;
        $this->metric = $metric;
        $this->timeElapsed = $timeElapsed;
        $this->exception = $exception;
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;
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
}
