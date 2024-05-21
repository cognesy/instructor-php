<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Schema\Data\TypeDetails;
use ReflectionFunction;
use ReflectionParameter;
use ReflectionProperty;

trait HandlesReflection
{
    static public function fromFunctionParameter(ReflectionFunction $function, ReflectionParameter $parameter) : static {
        $methodDocBlock = $function->getDocComment();
        $methodParamDoc =
        $type = $parameter->getType();
    }

    static public function fromReflectionProperty(ReflectionProperty $property) : static {
    }
}