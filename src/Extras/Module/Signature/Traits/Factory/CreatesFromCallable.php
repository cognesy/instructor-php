<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Factory;

use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Structure\FieldFactory;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use ReflectionFunction;

trait CreatesFromCallable
{
    public const DEFAULT_OUTPUT = 'result';

    static public function fromCallable(callable $callable): Signature {
        return (new self)->makeFromCallable($callable);
    }

    private function makeFromCallable(callable $callable): Signature {
        $reflection = new ReflectionFunction($callable);
        $description = $reflection->getDocComment();
        return new Signature(
            input: $this->makeInputSchema($callable),
            output: $this->makeOutputSchema($reflection),
            description: $description,
        );
    }

    private function makeInputSchema(callable $callable): Schema {
        return StructureFactory::fromCallable($callable, 'inputs')->schema();
    }

    private function makeOutputSchema(ReflectionFunction $reflection): Schema {
        $returnType = $reflection->getReturnType();
        if ($returnType === null) {
            throw new \InvalidArgumentException('Cannot build signature from callable with no return type');
        }
        $typeName = $returnType->getName();
        $name = self::DEFAULT_OUTPUT;
        return Structure::define('outputs', [
            FieldFactory::fromTypeName($name, $typeName)
        ])->schema();
    }
}