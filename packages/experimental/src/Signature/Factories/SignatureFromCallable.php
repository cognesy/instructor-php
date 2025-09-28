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

class SignatureFromCallable
{
    public const DEFAULT_OUTPUT = 'result';

    public function make(callable $callable): Signature {
        $reflection = new ReflectionFunction($callable);
        $description = $reflection->getDocComment();

        return new Signature(
            input: $this->inputSchemaFromCallable($callable),
            output: $this->makeOutputSchemaFromReflection($reflection),
            description: $description,
        );
    }

    private function inputSchemaFromCallable(callable $callable): Schema {
        return StructureFactory::fromCallable($callable, 'inputs')->schema();
    }

    private function makeOutputSchemaFromReflection(ReflectionFunction $reflection): Schema {
        $returnType = $reflection->getReturnType();
        if ($returnType === null) {
            throw new \InvalidArgumentException('Cannot build signature from callable with no return type');
        }
        $typeName = $returnType->getName();
        $name = self::DEFAULT_OUTPUT;
        try {
            $schema = Structure::define('outputs', [
                FieldFactory::fromTypeName($name, $typeName)
            ])->schema();
        } catch (Exception $e) {
            $functionName = $reflection->getName() .'($'. implode(',$', array_map(fn($p)=>$p->getName(), $reflection->getParameters())) .')';
            throw new InvalidArgumentException('Cannot build signature from callable `'.$functionName.'` with invalid return type `' . $typeName . '`: ' . $e->getMessage());
        }
        return $schema;
    }
}