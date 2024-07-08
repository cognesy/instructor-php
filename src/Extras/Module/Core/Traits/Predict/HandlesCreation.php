<?php
namespace Cognesy\Instructor\Extras\Module\Core\Traits\Predict;

use Cognesy\Instructor\Data\RequestInfo;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use InvalidArgumentException;

trait HandlesCreation
{
    public static function fromSignature(
        string|Signature $signature,
        string $description = ''
    ) : static {
        $instance = new static;
        $instance->using(signature: $instance->makeSignature($signature, $description));
        return $instance;
    }

    public static function fromRequest(RequestInfo $request, string $inputName, string $outputName) : static {
        $instance = new static;
        $instance->using(
            requestInfo: $request,
            signature: SignatureFactory::fromRequest($request, $inputName, $outputName),
        );
        return $instance;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    private function makeSignature(string|Signature $signature, string $description) : Signature {
        return match(true) {
            is_string($signature) => SignatureFactory::fromString($signature, $description),
            $signature instanceof Signature => $signature,
            default => throw new InvalidArgumentException('Invalid signature provided'),
        };
    }
}
