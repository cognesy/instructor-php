<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature\Factories;

use Cognesy\Dynamic\FieldFactory;
use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Experimental\Signature\Signature;
use Cognesy\Schema\Data\Schema\Schema;
use Exception;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class SignatureFromCallable
{
    public const DEFAULT_OUTPUT = 'result';

    private const DEFAULT_INPUT_NAME = 'inputs';
    private const DEFAULT_OUTPUT_NAME = 'outputs';

    public function make(callable $callable): Signature {
        $reflection = match(true) {
            $callable instanceof \Closure => new ReflectionFunction($callable),
            $this->isArrayCallable($callable) => $this->makeReflectionFromArrayCallable($callable),
            $this->isFunctionName($callable) => new ReflectionFunction($callable),
            default => throw new InvalidArgumentException('Unsupported callable type'),
        };

        $description = $reflection->getDocComment();

        return new Signature(
            input: StructureFactory::fromCallable($callable, self::DEFAULT_INPUT_NAME)->schema(),
            output: $this->makeOutputSchemaFromReflection($reflection),
            description: $description,
        );
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    private function makeOutputSchemaFromReflection(ReflectionFunctionAbstract $reflection): Schema {
        $returnType = $reflection->getReturnType();
        if ($returnType === null) {
            throw new \InvalidArgumentException('Cannot build signature from callable with no return type');
        }
        $typeName = $returnType->getName();
        $name = self::DEFAULT_OUTPUT;
        try {
            $schema = Structure::define(self::DEFAULT_OUTPUT_NAME, [
                FieldFactory::fromTypeName($name, $typeName)
            ])->schema();
        } catch (Exception $e) {
            $functionName = $reflection->getName() .'($'. implode(',$', array_map(fn($p)=>$p->getName(), $reflection->getParameters())) .')';
            throw new InvalidArgumentException(
                'Cannot build signature from callable `'.$functionName.'` with invalid return type `' . $typeName . '`: ' . $e->getMessage()
            );
        }
        return $schema;
    }

    private function isArrayCallable(callable $callable) : bool {
        return is_array($callable)
            && count($callable) === 2
            && (
                is_string($callable[0])
                || is_object($callable[0])
            )
            && is_string($callable[1]);
    }

    private function isFunctionName(callable $callable) : bool {
        return is_string($callable)
            && function_exists($callable);
    }

    private function makeReflectionFromArrayCallable(callable $callable) : ReflectionMethod {
        $class = is_string($callable[0]) ? $callable[0] : get_class($callable[0]);
        $method = $callable[1];
        if (!method_exists($class, $method)) {
            throw new InvalidArgumentException("Method `$method` not found in class `$class`");
        }
        return new ReflectionMethod($class, $method);
    }
}