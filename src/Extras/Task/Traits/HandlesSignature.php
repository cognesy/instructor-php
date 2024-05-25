<?php

namespace Cognesy\Instructor\Extras\Task\Traits;

use Cognesy\Instructor\Extras\Signature\Signature;

trait HandlesSignature
{
    protected Signature $signature;

    public function signature(): Signature {
        return $this->signature;
    }

    private function setSignature(string|Signature $signature): static {
        $this->signature = match(true) {
            is_string($signature) => Signature::fromString($signature),
            $signature instanceof Signature => $signature,
            default => throw new \InvalidArgumentException('Invalid signature')
        };
        return $this;
    }
}