<?php
namespace Cognesy\Instructor\Extras\Module\TaskData\Traits\Factory;

use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use Cognesy\Instructor\Extras\Module\TaskData\Contracts\HasInputOutputData;
use Cognesy\Instructor\Extras\Module\TaskData\StructureTaskData;
use Cognesy\Instructor\Extras\Module\TaskData\TaskData;
use Cognesy\Instructor\Extras\Structure\FieldFactory;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use ReflectionFunction;

trait CreatesFromCallable
{
    // TODO: Add attribute #[ResultName] to allow providing custom output name and description
    public const DEFAULT_OUTPUT = 'result';

    static public function fromCallable(callable $callable): TaskData {
        return (new self)->makeFromCallable($callable);
    }

    protected function makeFromCallable(callable $callable): TaskData {
        return new TaskData(
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
