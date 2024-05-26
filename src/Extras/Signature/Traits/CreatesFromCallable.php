<?php

namespace Cognesy\Instructor\Extras\Signature\Traits;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Signature\StructureSignature;
use Cognesy\Instructor\Extras\Structure\Structure;
use ReflectionFunction;

trait CreatesFromCallable
{
    public const DEFAULT_OUTPUT = 'result';

    static public function fromCallable(callable $callable): Signature {
        $inputSignature = Structure::fromCallable($callable, 'inputs');
        $reflection = new ReflectionFunction($callable);
        $description = $reflection->getDocComment();
        $returnType = $reflection->getReturnType();
        if ($returnType === null) {
            throw new \InvalidArgumentException('Cannot build signature from callable with no return type');
        }
        $typeName = $returnType->getName();
        // $name = $reflection->getName();
        $name = self::DEFAULT_OUTPUT;
        $outputSignature = Structure::define('outputs', [
            Field::fromTypeName($name, $typeName)
        ]);
        return new StructureSignature(
            inputs: $inputSignature,
            outputs: $outputSignature,
            description: $description,
        );
    }
}