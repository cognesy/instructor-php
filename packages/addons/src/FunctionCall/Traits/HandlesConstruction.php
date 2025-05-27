<?php

namespace Cognesy\Addons\FunctionCall\Traits;

use Cognesy\Addons\FunctionCall\FunctionCall;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Schema\Utils\FunctionInfo;
use ReflectionFunction;

trait HandlesConstruction
{
    static public function fromFunctionName(string $function) : FunctionCall {
        $functionInfo = FunctionInfo::fromFunctionName($function);
        $structure = StructureFactory::fromFunctionName($function, '', '');
        return self::fromFunctionInfo($functionInfo, $structure);
    }

    static public function fromMethodName(string $class, string $method) : FunctionCall {
        $functionInfo = FunctionInfo::fromMethodName($class, $method);
        $structure = StructureFactory::fromMethodName($class, $method, '', '');
        return self::fromFunctionInfo($functionInfo, $structure);
    }

    static public function fromCallable(callable $callable) : FunctionCall {
        $functionInfo = new FunctionInfo(new ReflectionFunction($callable));
        $structure = StructureFactory::fromCallable($callable, '', '');
        return self::fromFunctionInfo($functionInfo, $structure);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    static private function fromFunctionInfo(FunctionInfo $functionInfo, Structure $structure) : FunctionCall {
        $functionName = $functionInfo->getShortName();
        $functionDescription = $functionInfo->getDescription();
        return new FunctionCall($functionName, $functionDescription, $structure);
    }
}
