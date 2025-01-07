<?php
namespace Cognesy\Instructor\Experimental\Module\Core\Traits\Predictor;

use Cognesy\Instructor\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Experimental\Module\Signature\SignatureFactory;
use Cognesy\Instructor\Features\Core\Data\StructuredOutputRequestInfo;
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

    public static function fromRequest(StructuredOutputRequestInfo $request, string $inputName, string $outputName) : static {
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
