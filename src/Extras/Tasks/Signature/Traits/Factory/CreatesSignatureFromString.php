<?php
namespace Cognesy\Instructor\Extras\Tasks\Signature\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Signature\StructureSignature;
use Cognesy\Instructor\Utils\Pipeline;
use InvalidArgumentException;

trait CreatesSignatureFromString
{
    static public function fromString(string $signatureString): Signature {
        if (!str_contains($signatureString, Signature::ARROW)) {
            throw new InvalidArgumentException('Invalid signature string, missing arrow -> marker separating inputs and outputs');
        }
        $signatureString = (new Pipeline)
            ->through(fn(string $str) => trim($str))
            ->through(fn(string $str) => str_replace("\n", ' ', $str))
            ->through(fn(string $str) => str_replace(Signature::ARROW, '>', $str))
            ->process($signatureString);
        // split inputs and outputs
        [$inputs, $outputs] = explode('>', $signatureString);
        $signature = new StructureSignature(
            inputs: StructureFactory::fromString('inputs', $inputs),
            outputs: StructureFactory::fromString('outputs', $outputs)
        );
        return $signature;
    }
}
