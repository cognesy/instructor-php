<?php
namespace Cognesy\Instructor\Extras\Module\CallData\Traits\Factory;

use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\CallData\CallData;
use Cognesy\Instructor\Extras\Structure\StructureFactory;

trait CreatesFromSignature
{
    static public function fromSignature(Signature $signature): CallData {
        $callData = new CallData(
            input: StructureFactory::fromSchema('inputs', $signature->toInputSchema()),
            output: StructureFactory::fromSchema('outputs', $signature->toOutputSchema()),
            signature: $signature,
        );
        return $callData;
    }
}
