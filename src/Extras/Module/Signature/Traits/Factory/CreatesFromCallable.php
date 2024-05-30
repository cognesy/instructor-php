<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\FieldFactory;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\Signature\StructureSignature;
use ReflectionFunction;

trait CreatesFromCallable
{
    public const DEFAULT_OUTPUT = 'result';

    static public function fromCallable(callable $callable): HasSignature {
        $inputStructure = StructureFactory::fromCallable($callable, 'inputs');
        $reflection = new ReflectionFunction($callable);
        $description = $reflection->getDocComment();
        $returnType = $reflection->getReturnType();
        if ($returnType === null) {
            throw new \InvalidArgumentException('Cannot build signature from callable with no return type');
        }
        $typeName = $returnType->getName();
        // $name = $reflection->getName();
        $name = self::DEFAULT_OUTPUT;
        $outputStructure = Structure::define('outputs', [
            FieldFactory::fromTypeName($name, $typeName)
        ]);
        return new StructureSignature(
            inputs: $inputStructure,
            outputs: $outputStructure,
            description: $description,
        );
    }
}