<?php

namespace Cognesy\Instructor\Extras\Tasks\Task\Traits;

use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Signature\SignatureFactory;

trait HandlesSignature
{
    protected Signature $signature;

    public function signature(): Signature {
        return $this->signature;
    }

    private function setSignature(string|Signature $signature): static {
        $this->signature = match(true) {
            is_string($signature) && str_contains($signature, Signature::ARROW) => SignatureFactory::fromString($signature),
            is_string($signature) => SignatureFactory::fromClassMetadata($signature),
            $signature instanceof Signature => $signature,
            default => throw new \InvalidArgumentException('Invalid signature')
        };
        return $this;
    }
}
