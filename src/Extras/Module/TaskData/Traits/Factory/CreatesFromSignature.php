<?php
namespace Cognesy\Instructor\Extras\Module\TaskData\Traits\Factory;

use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\TaskData\TaskData;
use Cognesy\Instructor\Extras\Structure\StructureFactory;

trait CreatesFromSignature
{
    static public function fromSignature(Signature $signature): TaskData {
        $taskData = new TaskData(
            input: StructureFactory::fromSchema('inputs', $signature->toInputSchema()),
            output: StructureFactory::fromSchema('outputs', $signature->toOutputSchema()),
            signature: $signature,
        );
        return $taskData;
    }
}
