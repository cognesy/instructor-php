<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Factory;

use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Utils\Pipeline;
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
        $signatureString = (new Pipeline)
            ->through(fn(string $str) => trim($str))
            ->through(fn(string $str) => str_replace("\n", ' ', $str))
            ->through(fn(string $str) => str_replace(Signature::ARROW, '>', $str))
            ->process($signatureString);
        // split inputs and outputs
        [$inputs, $outputs] = explode('>', $signatureString);

        return new Signature(
            input: StructureFactory::fromString('inputs', $inputs)->schema(),
            output: StructureFactory::fromString('outputs', $outputs)->schema(),
            description: $description,
        );
    }
}
