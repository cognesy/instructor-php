<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Traits\StructureFactory;

use Closure;
use Cognesy\Dynamic\FieldFactory;
use Cognesy\Dynamic\Structure;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Reflection\FunctionInfo;

trait CreatesStructureFromCallables
{
    static public function fromFunctionName(string $function, ?string $name = null, ?string $description = null) : Structure {
        return self::makeFromFunctionInfo(FunctionInfo::fromFunctionName($function), $name, $description);
    }

    static public function fromMethodName(string $class, string $method, ?string $name = null, ?string $description = null) : Structure {
        return self::makeFromFunctionInfo(FunctionInfo::fromMethodName($class, $method), $name, $description);
    }

    static public function fromCallable(callable $callable, ?string $name = null, ?string $description = null) : Structure {
        $closure = match(true) {
            $callable instanceof Closure => $callable,
            default => Closure::fromCallable($callable),
        };
        return self::makeFromFunctionInfo(FunctionInfo::fromClosure($closure), $name, $description);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    static private function makeFromFunctionInfo(
        FunctionInfo $functionInfo,
        ?string $name = null,
        ?string $description = null
    ) : Structure {
        $functionName = $name ?? $functionInfo->getShortName();
        $functionDescription = $description ?? $functionInfo->getDescription();
        $arguments = self::makeArgumentFields($functionInfo);
        return Structure::define($functionName, $arguments, $functionDescription);
    }

    static private function makeArgumentFields(FunctionInfo $functionInfo) : array {
        $arguments = [];
        foreach ($functionInfo->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $parameterDescription = $functionInfo->getParameterDescription($parameterName);
            $isOptional = $parameter->isOptional();
            $isVariadic = $parameter->isVariadic();
            $paramType = $parameter->getType()?->getName();
            $typeDetails = match($isVariadic) {
                true => TypeDetails::collection($paramType),
                default => TypeDetails::fromTypeName($paramType),
            };
            $defaultValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
            $arguments[] = FieldFactory::fromTypeDetails($parameterName, $typeDetails, $parameterDescription)
                ->optional($isOptional)
                ->withDefaultValue($defaultValue);
        }
        return $arguments;
    }
}
