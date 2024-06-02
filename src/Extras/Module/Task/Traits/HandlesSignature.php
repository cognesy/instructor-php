<?php

namespace Cognesy\Instructor\Extras\Module\Task\Traits;

use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
trait HandlesSignature
{
    protected Signature $signature;

    static public function withSignature(Signature $signature): static {
        $task = new static();
        $task->signature = $task->initSignature($signature);
        return $task;
    }

    public function signature() : string|Signature {
        throw new Exception('Implement signature() or initialize task with signature using withSignature().');
    }

    public function getSignature(): Signature {
        if (!isset($this->signature)) {
            $this->signature = $this->initSignature($this->signature());
        }
        return $this->signature;
    }

    protected function initSignature(string|Signature $signature) : Signature {
        $instance = match(true) {
            is_string($signature) && str_contains($signature, Signature::ARROW) => SignatureFactory::fromString($signature),
            // is_string($signature) => SignatureFactory::fromClassMetadata($signature),
            $signature instanceof Signature => $signature,
            default => throw new InvalidArgumentException('Object is not instance of Signature: ' . get_class($signature))
        };
        return $instance;
    }
}
