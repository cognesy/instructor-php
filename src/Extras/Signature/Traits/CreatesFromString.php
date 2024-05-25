<?php
namespace Cognesy\Instructor\Extras\Signature\Traits;

use Cognesy\Instructor\Extras\Signature\Signature;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Utils\Pipeline;
use InvalidArgumentException;

trait CreatesFromString
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
        $signature = new Signature(
            inputs: Structure::fromString('inputs', $inputs),
            outputs: Structure::fromString('outputs', $outputs)
        );
        return $signature;
    }
}
