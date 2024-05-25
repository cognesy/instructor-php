<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Utils\FunctionInfo;
use ReflectionFunction;

trait CreatesFromCallables
{
    static public function fromFunctionName(string $function, string $name = null, string $description = null) : static {
        return self::fromFunctionInfo(FunctionInfo::fromFunctionName($function), $name, $description);
    }

    static public function fromMethodName(string $class, string $method, string $name = null, string $description = null) : static {
        return self::fromFunctionInfo(FunctionInfo::fromMethodName($class, $method), $name, $description);
    }

    static public function fromCallable(callable $callable, string $name = null, string $description = null) : static {
        $functionInfo = new FunctionInfo(new ReflectionFunction($callable));
        return self::fromFunctionInfo($functionInfo, $name, $description);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    static private function fromFunctionInfo(FunctionInfo $functionInfo, string $name = null, string $description = null) : static {
        $functionName = $name ?? $functionInfo->getShortName();
        $functionDescription = $description ?? $functionInfo->getDescription();
        $arguments = self::makeArgumentFields($functionInfo);
        return Structure::define($functionName, $arguments, $functionDescription);
    }

    static private function makeArgumentFields(FunctionInfo $functionInfo) : array {
        $arguments = [];
        $typeDetailsFactory = new TypeDetailsFactory;
        foreach ($functionInfo->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $parameterDescription = $functionInfo->getParameterDescription($parameterName);
            $isOptional = $parameter->isOptional();
            $isVariadic = $parameter->isVariadic();
            $typeDetails = match($isVariadic) {
                true => $typeDetailsFactory->arrayType($parameter->getType()),
                default => $typeDetailsFactory->fromTypeName($parameter->getType())
            };
            $arguments[] = Field::fromTypeDetails($parameterName, $typeDetails, $parameterDescription)->optional($isOptional);
        }
        return $arguments;
    }
}
