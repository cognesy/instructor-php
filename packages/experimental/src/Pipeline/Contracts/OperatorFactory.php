<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline\Contracts;

use Cognesy\Experimental\Pipeline\OperatorSpec;

interface OperatorFactory
{
    /**
     * Creates an Operator instance from a given specification.
     *
     * @param OperatorSpec $spec The specification of the operator.
     * @return Operator The hydrated operator instance.
     */
    public function create(OperatorSpec $spec): Operator;
}