<?php declare(strict_types=1);

namespace Cognesy\Evals\Contracts;

use Cognesy\Evals\Observation;

/**
 * Interface for generating observations for a given subject.
 * @template T
 */
interface CanGenerateObservations
{
    /**
     * Evaluates whether the given subject is acceptable.
     *
     * @param T $subject The subject to evaluate.
     * @return bool True if the subject is acceptable, false otherwise.
     */
    public function accepts(mixed $subject) : bool;

    /**
     * Generates a series of observations for the given subject.
     *
     * @param T $subject The subject to observe.
     * @return iterable<Observation> A collection of observations.
     */
    public function observations(mixed $subject) : iterable;
}
