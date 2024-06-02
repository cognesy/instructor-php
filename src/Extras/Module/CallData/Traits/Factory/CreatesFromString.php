<?php
namespace Cognesy\Instructor\Extras\Module\CallData\Traits\Factory;

use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use Cognesy\Instructor\Extras\Module\CallData\CallData;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Utils\Pipeline;
use InvalidArgumentException;

trait CreatesFromString
{
    static public function fromString(string $signatureString): CallData {
        if (!str_contains($signatureString, Signature::ARROW)) {
            throw new InvalidArgumentException('Invalid signature string, missing arrow -> marker separating inputs and outputs');
        }
        $processedSignature = (new Pipeline)
            ->through(fn(string $str) => trim($str))
            ->through(fn(string $str) => str_replace("\n", ' ', $str))
            ->through(fn(string $str) => str_replace(Signature::ARROW, '>', $str))
            ->process($signatureString);
        // split inputs and outputs
        [$inputs, $outputs] = explode('>', $processedSignature);

        $callData = new CallData(
            input: StructureFactory::fromString('inputs', $inputs),
            output: StructureFactory::fromString('outputs', $outputs),
            signature: SignatureFactory::fromString($signatureString),
        );
        return $callData;
    }
}
