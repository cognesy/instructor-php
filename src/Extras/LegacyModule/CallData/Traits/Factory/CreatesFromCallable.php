<?php
namespace Cognesy\Instructor\Extras\Module\CallData\Traits\Factory;

use Cognesy\Experimental\Module\Signature\SignatureFactory;
use Cognesy\Instructor\Extras\Module\CallData\CallData;
use Cognesy\Instructor\Extras\Structure\FieldFactory;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use ReflectionFunction;

trait CreatesFromCallable
{
    // TODO: Add attribute #[ResultName] to allow providing custom output name and description
    public const DEFAULT_OUTPUT = 'result';

    static public function fromCallable(callable $callable): CallData {
        return (new self)->makeFromCallable($callable);
    }

    protected function makeFromCallable(callable $callable): CallData {
        return new CallData(
            input: StructureFactory::fromCallable($callable, 'inputs'),
            output: $this->makeOutputStructure($callable),
            signature: SignatureFactory::fromCallable($callable),
        );
    }

    protected function makeOutputStructure(callable $callable) : Structure {
        // make output structure from return type
        $reflection = new ReflectionFunction($callable);
        $returnType = $reflection->getReturnType();
        if ($returnType === null) {
            throw new \InvalidArgumentException('Cannot build signature from callable with no return type');
        }
        return Structure::define('outputs', [
            FieldFactory::fromTypeName(
                name: self::DEFAULT_OUTPUT,
                typeName: $returnType->getName()
            )
        ]);
    }
}
