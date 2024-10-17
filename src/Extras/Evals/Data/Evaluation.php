<?php

namespace Cognesy\Instructor\Extras\Evals\Data;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;

class Evaluation
{
    readonly public string $id;
    public ?DateTime $startedAt = null;
    public float $timeElapsed = 0.0;
    public ?Usage $usage = null;
    public array $metadata = [];

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
        $this->metadata = $metadata;
    }

    public function metric() : Metric {
        return $this->metric;
    }

    public function feedback() : Feedback {
        return $this->feedback;
    }

    public function meta(string $key, mixed $default = null) : mixed {
        return $this->metadata[$key] ?? $default;
    }

    public function withMeta(string $key, mixed $value) : self {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function hasMetric(string $metricName) : bool {
        return $this->metric?->name() === $metricName;
    }
}
