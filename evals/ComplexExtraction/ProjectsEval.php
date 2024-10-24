<?php

namespace Cognesy\Evals\ComplexExtraction;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExecution;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Observation;

class ProjectsEval implements CanObserveExecution
{
    private string $key;
    private array $expectations;

    public function __construct(string $key, array $expectations) {
        $this->key = $key;
        $this->expectations = $expectations;
    }

    /**
     * @return Observation
     */
    public function observe(Execution $execution): Observation {
        $expectedEvents = $this->expectations['events'];
        /** @var ProjectEvents $events */
        $events = $execution->get('response')?->value();
        $result = ($expectedEvents - count($events->events)) / $expectedEvents;
        return Observation::make(
            type: 'metric',
            key: $this->key,
            value: $result,
            metadata: [
                'executionId' => $execution->id(),
                'unit' => 'fraction',
                'format' => '%.2f',
            ],
        );
    }
}
