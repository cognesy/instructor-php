<?php declare(strict_types=1);

namespace Cognesy\Evals\Observers\Measure;

use Cognesy\Evals\Contracts\CanGenerateObservations;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Experiment;
use Cognesy\Evals\Observation;

/**
 * Class TokenUsage
 *
 * Observer implementation that generates token-usage observations
 * for a given subject.
 *
 * @template T
 * @implements CanGenerateObservations<T>
 */
class TokenUsageObserver implements CanGenerateObservations
{
    /**
     * Checks if the given subject is an instance of Experiment or Execution.
     *
     * @param mixed $subject The subject to be checked.
     * @return bool Returns true if the subject is of accepted type.
     */
    public function accepts(mixed $subject): bool {
        return match(true) {
            $subject instanceof Experiment => true,
            $subject instanceof Execution => true,
        };
    }

    /**
     * Generates observations for the subject.
     *
     * @param T $subject The subject for which observations need to be generated.
     * @return iterable<Observation> Yields a series of Observation objects.
     */
    public function observations(mixed $subject): iterable {
        yield from match(true) {
            $subject instanceof Experiment => $this->experimentUsage($subject),
            $subject instanceof Execution => $this->executionUsage($subject),
        };
    }

    // INTERNAL ////////////////////////////////////////////////

    /**
     * Generate observations from an Experiment
     *
     * @param Execution $execution Observation subject.
     * @return iterable<\Cognesy\Evals\Observation> Yields Observation objects with token usage.
     */
    private function executionUsage(Execution $execution): iterable {
        $observations = [
            'execution.tokens.total' => $execution->usage()->total(),
            'execution.tokens.output' => $execution->usage()->output(),
            'execution.tokens.input' => $execution->usage()->input(),
            'execution.tokens.cache' => $execution->usage()->cache(),
        ];
        foreach ($observations as $key => $value) {
            yield $this->makeObservation('executionId', $execution->id(), $key, $value);
        }
    }

    /**
     * Generate observations from an Experiment
     *
     * @param Experiment $experiment Observation subject.
     * @return iterable<Observation> Yields Observation objects with token usage.
     */
    private function experimentUsage(Experiment $experiment): iterable {
        $observations = [
            'experiment.tokens.total' => $experiment->usage()->total(),
            'experiment.tokens.output' => $experiment->usage()->output(),
            'experiment.tokens.input' => $experiment->usage()->input(),
            'experiment.tokens.cache' => $experiment->usage()->cache(),
        ];
        foreach ($observations as $key => $value) {
            yield $this->makeObservation('experimentId', $experiment->id(), $key, $value);
        }
    }

    /**
     * Create an Observation.
     *
     * @param string $id Object identifier.
     * @param string $key The key for the observation metric.
     * @param mixed $value The value for the observation metric.
     * @return Observation The created Observation object.
     */
    private function makeObservation(string $idName, string $id, string $key, mixed $value): Observation {
        return Observation::make(
            type: 'metric',
            key: $key,
            value: $value,
            metadata: [
                $idName => $id,
                'unit' => 'tokens',
                'format' => '%d',
                'aggregationMethod' => 'sum',
            ],
        );
    }
}
