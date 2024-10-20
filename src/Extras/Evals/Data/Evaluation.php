<?php

namespace Cognesy\Instructor\Extras\Evals\Data;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\DataMap;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;

class Evaluation
{
    readonly private string $id;
    private ?DateTime $startedAt;
    private float $timeElapsed = 0.0;
    private ?Usage $usage;
    private DataMap $data;

    public ?Metric $metric = null;
    public ?Feedback $feedback = null;

    public function __construct(
        ?Metric $metric = null,
        ?Feedback $feedback = null,
        ?Usage $usage = null,
        array $metadata = [],
    ) {
        $this->id = Uuid::uuid4();
        $this->startedAt = new DateTime();
        $this->metric = $metric;
        $this->feedback = $feedback;
        $this->usage = $usage;
        $this->data = new DataMap($metadata);
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

    public function withTimeElapsed(float $timeElapsed) : self {
        $this->timeElapsed = $timeElapsed;
        return $this;
    }

    public function usage() : Usage {
        return $this->usage;
    }

    public function data() : DataMap {
        return $this->data;
    }

    public function feedback() : Feedback {
        return $this->feedback;
    }

    public function metric() : Metric {
        return $this->metric;
    }

    public function hasMetric(string $metricName) : bool {
        return $this->metric?->name() === $metricName;
    }
}
