<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature\Factories;

use Cognesy\Dynamic\StructureFactory;
use Cognesy\Experimental\Signature\Signature;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use InvalidArgumentException;

class SignatureFromString
{
    public function make(string $signatureString, ?string $description = null): Signature {
        if (empty($signatureString)) {
            throw new InvalidArgumentException('Invalid signature string, empty string');
        }

        if (!str_contains($signatureString, Signature::ARROW)) {
            throw new InvalidArgumentException('Invalid signature string, missing arrow -> marker separating inputs and outputs');
        }

        $signatureString = $this->normalizeSignatureString($signatureString);
        [$inputs, $outputs] = explode('>', $signatureString);

        return new Signature(
            input: StructureFactory::fromString('inputs', $inputs)->schema(),
            output: StructureFactory::fromString('outputs', $outputs)->schema(),
            description: $description,
        );
    }

    private function normalizeSignatureString(string $signatureString) : string {
        return Pipeline::builder()
            ->through(fn(string $str) => trim($str))
            ->through(fn(string $str) => str_replace("\n", ' ', $str))
            ->through(fn(string $str) => str_replace(Signature::ARROW, '>', $str))
            ->executeWith(ProcessingState::with($signatureString))
            ->value();
    }
}
