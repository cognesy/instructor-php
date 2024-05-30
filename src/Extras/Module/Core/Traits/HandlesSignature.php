<?php

namespace Cognesy\Instructor\Extras\Module\Core\Traits;

use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use InvalidArgumentException;

trait HandlesSignature
{
    protected HasSignature $signature;

    public function getSignature(): HasSignature {
        if (!isset($this->signature)) {
            $this->signature = $this->initSignature($this->signature());
        }
        return $this->signature;
    }

    protected function initSignature(string|HasSignature $signature) : HasSignature {
        $instance = match(true) {
            is_string($signature) && str_contains($signature, HasSignature::ARROW) => SignatureFactory::fromString($signature),
            is_string($signature) => SignatureFactory::fromClassMetadata($signature),
            $signature instanceof HasSignature => $signature,
            default => throw new InvalidArgumentException('Object is not instance of Signature: ' . get_class($signature))
        };
        return $instance;
    }
}
