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

    public function __construct(
        public ?Metric $metric = null,
        public ?Feedback $feedback = null,
        public ?Usage $usage = null,
    ) {
        $this->id = Uuid::uuid4();
        $this->startedAt = new DateTime();
    }

    public function metric() : Metric {
        return $this->metric;
    }

    public function feedback() : Feedback {
        return $this->feedback;
    }
}