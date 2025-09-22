<?php declare(strict_types=1);

namespace Cognesy\Addons\FunctionCall;

use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Schema\Reflection\FunctionInfo;

class FunctionCallFactory
{
    static public function fromFunctionName(string $function) : FunctionCall {
        $functionInfo = FunctionInfo::fromFunctionName($function);
        $structure = StructureFactory::fromFunctionName($function, '', '');
        return self::makeFromFunctionInfo(
            $functionInfo,
            $structure
        );
    }

    static public function fromMethodName(string $class, string $method) : FunctionCall {
        $functionInfo = FunctionInfo::fromMethodName($class, $method);
        $structure = StructureFactory::fromMethodName($class, $method, '', '');
        return self::makeFromFunctionInfo(
            $functionInfo,
            $structure
        );
    }

    static public function fromCallable(callable $callable) : FunctionCall {
        $functionInfo = FunctionInfo::fromClosure($callable);
        $structure = StructureFactory::fromCallable($callable, '', '');
        return self::makeFromFunctionInfo(
            $functionInfo,
            $structure
        );
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    static private function makeFromFunctionInfo(FunctionInfo $functionInfo, Structure $structure) : FunctionCall {
        $functionName = $functionInfo->getShortName();
        $functionDescription = $functionInfo->getDescription();
        return new FunctionCall(
            $functionName,
            $functionDescription,
            $structure
        );
    }
}