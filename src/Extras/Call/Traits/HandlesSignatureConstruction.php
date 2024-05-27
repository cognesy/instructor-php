<?php

namespace Cognesy\Instructor\Extras\Call\Traits;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Utils\FunctionInfo;
use ReflectionFunction;

trait HandlesConstruction
{
    static public function fromFunctionName(string $function) : static {
        $functionInfo = FunctionInfo::fromFunctionName($function);
        $structure = Structure::fromFunctionName($function, '', '');
        return self::fromFunctionInfo($functionInfo, $structure);
    }

    static public function fromMethodName(string $class, string $method) : static {
        $functionInfo = FunctionInfo::fromMethodName($class, $method);
        $structure = Structure::fromMethodName($class, $method, '', '');
        return self::fromFunctionInfo($functionInfo, $structure);
    }

    static public function fromCallable(callable $callable) : static {
        $functionInfo = new FunctionInfo(new ReflectionFunction($callable));
        $structure = Structure::fromCallable($callable, '', '');
        return self::fromFunctionInfo($functionInfo, $structure);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    static private function fromFunctionInfo(FunctionInfo $functionInfo, Structure $structure) : static {
        $functionName = $functionInfo->getShortName();
        $functionDescription = $functionInfo->getDescription();
        return new static($functionName, $functionDescription, $structure);
    }
}
