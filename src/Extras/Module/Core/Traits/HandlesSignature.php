<?php

namespace Cognesy\Instructor\Extras\Module\Core\Traits;

use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use Exception;

trait HandlesSignature
{
    abstract public function signature() : string|Signature;

    protected function getSignature() : Signature {
        if (!isset($this->signature)) {
            $signature = $this->signature();
            $this->signature = match(true) {
                is_string($signature) && str_contains($signature, Signature::ARROW) => SignatureFactory::fromString($signature),
                is_string($signature) && (is_subclass_of($signature, HasSignature::class)) => (new $signature)->signature(),
                $signature instanceof HasSignature => $signature->signature(),
                $signature instanceof Signature => $signature,
                default => throw new Exception('Invalid signature type: ' . gettype($signature))
            };
        }
        return $this->signature;
    }
}