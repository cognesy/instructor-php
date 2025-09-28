<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Contracts;

use Cognesy\Experimental\Pipeline2\Op;

interface OperatorFactory
{
    /**
     * Creates an Operator instance from a given specification.
     *
     * @param Op $spec The specification of the operator.
     * @return Operator The hydrated operator instance.
     */
    public function create(Op $spec): Operator;
}