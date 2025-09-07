<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\MessageStoreParameters;

trait HandlesConversion
{
    public function toArray() : array {
        return $this->parameters;
    }

    public function keys() : array {
        return array_keys($this->parameters);
    }

    public function values() : array {
        return array_values($this->parameters);
    }
}