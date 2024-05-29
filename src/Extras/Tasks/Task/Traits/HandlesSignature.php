<?php

namespace Cognesy\Instructor\Extras\Tasks\Task\Traits;

use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Signature\SignatureFactory;
use InvalidArgumentException;

trait HandlesSignature
{
    protected Signature $signature;

    public function getSignature(): Signature {
        if (!isset($this->signature)) {
            $this->signature = $this->initSignature($this->signature());
        }
        return $this->signature;
    }

    protected function initSignature(string|Signature $signature) : Signature {
        $instance = match(true) {
            is_string($signature) && str_contains($signature, Signature::ARROW) => SignatureFactory::fromString($signature),
            is_string($signature) => SignatureFactory::fromClassMetadata($signature),
            $signature instanceof Signature => $signature,
            default => throw new InvalidArgumentException('Object is not instance of Signature: ' . get_class($signature))
        };
        return $instance;
    }
}
