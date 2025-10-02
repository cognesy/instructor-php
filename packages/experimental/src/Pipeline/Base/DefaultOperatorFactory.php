<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline\Base;

use Cognesy\Experimental\Pipeline\Contracts\Operator;
use Cognesy\Experimental\Pipeline\Contracts\OperatorFactory;
use Cognesy\Experimental\Pipeline\OperatorSpec;
use ReflectionClass;

/**
 * A default factory that uses reflection to instantiate operators.
 */
final class DefaultOperatorFactory implements OperatorFactory
{
    #[\Override]
    public function create(OperatorSpec $spec): Operator {
        $operator = (new ReflectionClass($spec->class))->newInstanceArgs($spec->args);
        if (!$operator instanceof Operator) {
            throw new \RuntimeException("Failed to create operator of class {$spec->class}");
        }
        return $operator;
    }
}
