<?php declare(strict_types=1);

namespace Cognesy\Addons\FunctionCall;

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\CallableSchemaFactory;
use Cognesy\Schema\Data\Schema;

class FunctionCallFactory
{
    static public function fromFunctionName(string $function) : FunctionCall {
        $schema = (new CallableSchemaFactory())->fromFunctionName($function);
        return self::makeFromSchema($schema);
    }

    static public function fromMethodName(string $class, string $method) : FunctionCall {
        $schema = (new CallableSchemaFactory())->fromMethodName($class, $method);
        return self::makeFromSchema($schema);
    }

    /**
     * @param callable(): mixed $callable
     */
    static public function fromCallable(callable $callable) : FunctionCall {
        $schema = (new CallableSchemaFactory())->fromCallable($callable);
        return self::makeFromSchema($schema);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    static private function makeFromSchema(Schema $schema) : FunctionCall {
        $arguments = Structure::fromSchema($schema);

        return new FunctionCall(
            $arguments->name(),
            $arguments->description(),
            $arguments,
        );
    }
}
