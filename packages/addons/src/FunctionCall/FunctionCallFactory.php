<?php declare(strict_types=1);

namespace Cognesy\Addons\FunctionCall;

use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureFactory;

class FunctionCallFactory
{
    static public function fromFunctionName(string $function) : FunctionCall {
        $structure = (new StructureFactory())->fromFunctionName($function, '', '');
        return self::makeFromStructure($structure);
    }

    static public function fromMethodName(string $class, string $method) : FunctionCall {
        $structure = (new StructureFactory())->fromMethodName($class, $method, '', '');
        return self::makeFromStructure($structure);
    }

    /**
     * @param callable(): mixed $callable
     */
    static public function fromCallable(callable $callable) : FunctionCall {
        $structure = (new StructureFactory())->fromCallable($callable, '', '');
        return self::makeFromStructure($structure);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    static private function makeFromStructure(Structure $structure) : FunctionCall {
        return new FunctionCall(
            $structure->name(),
            $structure->description(),
            $structure
        );
    }
}
