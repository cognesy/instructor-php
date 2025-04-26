<?php

namespace Evals\ComplexExtraction;

use Cognesy\Evals\Contracts\CanObserveExecution;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Observation;

class ProjectsEval implements CanObserveExecution
{
    private string $key;
    private array $expectations;

    public function __construct(string $key, array $expectations) {
        $this->key = $key;
        $this->expectations = $expectations;
    }

    /**
     * @return \Cognesy\Evals\Observation
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
