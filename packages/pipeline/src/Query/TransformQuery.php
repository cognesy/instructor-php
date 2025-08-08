<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Query;

use Cognesy\Pipeline\ProcessingState;

/**
 * Fluent interface for state transformations.
 */
final class TransformQuery
{
    public function __construct(
        private readonly ProcessingState $state
    ) {}

}