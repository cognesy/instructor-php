<?php

namespace Cognesy\Addons\Evals\Observers\Measure;

use Cognesy\Addons\Evals\Contracts\CanGenerateObservations;
use Cognesy\Addons\Evals\Execution;
use Cognesy\Addons\Evals\Experiment;
use Cognesy\Addons\Evals\Observation;

class DurationObserver implements CanGenerateObservations
{
    public function accepts(mixed $subject): bool {
        return match(true) {
            $subject instanceof Experiment => true,
            $subject instanceof Execution => true,
        };
    }

    public function observations(mixed $subject): iterable {
        yield match(true) {
            $subject instanceof Experiment => $this->experimentDuration($subject),
            $subject instanceof Execution => $this->executionDuration($subject),
        };
    }

    private function experimentDuration(Experiment $experiment): Observation {
        return Observation::make(
            type: 'metric',
            key: 'experiment.timeElapsed',
            value: $experiment->timeElapsed(),
            metadata: [
                'experimentId' => $experiment->id(),
                'unit' => 'seconds',
                'format' => '%.2f',
                'aggregationMethod' => 'sum',
            ],
        );
    }

    private function executionDuration(Execution $execution): Observation {
        return Observation::make(
            type: 'metric',
            key: 'execution.timeElapsed',
            value: $execution->timeElapsed(),
            metadata: [
                'executionId' => $execution->id(),
                'unit' => 'seconds',
                'format' => '%.2f',
                'aggregationMethod' => 'sum',
            ],
        );
    }
}
