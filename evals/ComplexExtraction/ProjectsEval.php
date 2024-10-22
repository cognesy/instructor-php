<?php

namespace Cognesy\Evals\ComplexExtraction;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExecution;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Observation;
use Cognesy\Instructor\Extras\Sequence\Sequence;

class ProjectsEval implements CanObserveExecution
{
    public array $expectations;

    public function __construct(array $expectations) {
        $this->expectations = $expectations;
    }

    /**
     * @return Observation
     */
    public function observe(Execution $execution): Observation {
        $expectedEvents = $this->expectations['events'];
        /** @var Sequence $events */
        $events = $execution->get('response')?->value();
        $result = ($expectedEvents - count($events->list)) / $expectedEvents;
        return Observation::make(
            type: 'metric',
            key: 'execution.percentFound',
            value: $result,
            metadata: [
                'executionId' => $execution->id(),
                'unit' => 'percentage',
            ],
        );
    }
}
