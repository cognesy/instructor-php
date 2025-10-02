<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Base;

use Cognesy\Experimental\Pipeline2\Contracts\Operator;
use Cognesy\Experimental\Pipeline2\Contracts\OperatorFactory;
use Cognesy\Experimental\Pipeline2\Op;
use ReflectionClass;

/**
 * A default factory that uses reflection to instantiate operators.
 */
final class BaseOperatorFactory implements OperatorFactory
{
    #[\Override]
    public function create(Op $spec): Operator {
        $operator = (new ReflectionClass($spec->class))->newInstanceArgs($spec->args);
        if (!$operator instanceof Operator) {
            throw new \RuntimeException("Failed to create operator of class {$spec->class}");
        }
        return $operator;
    }
}
