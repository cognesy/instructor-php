<?php
namespace Cognesy\Experimental\Module\Signature\Traits\Factory;

use Cognesy\Dynamic\StructureFactory;
use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use InvalidArgumentException;

trait CreatesSignatureFromString
{
    static public function fromString(
        string $signatureString,
        string $description = '',
    ): Signature {
        if (empty($signatureString)) {
            throw new InvalidArgumentException('Invalid signature string, empty string');
        }
        if (!str_contains($signatureString, Signature::ARROW)) {
            throw new InvalidArgumentException('Invalid signature string, missing arrow -> marker separating inputs and outputs');
        }
        $signatureString = Pipeline::builder()
            ->through(fn(string $str) => trim($str))
            ->through(fn(string $str) => str_replace("\n", ' ', $str))
            ->through(fn(string $str) => str_replace(Signature::ARROW, '>', $str))
            ->executeWith(ProcessingState::with($signatureString))
            ->value();

        // split inputs and outputs
        [$inputs, $outputs] = explode('>', $signatureString);

        return new Signature(
            input: StructureFactory::fromString('inputs', $inputs)->schema(),
            output: StructureFactory::fromString('outputs', $outputs)->schema(),
            description: $description,
        );
    }
}
