<?php

namespace Cognesy\Evals\Traits\Observation;

use Cognesy\Utils\DataMap;
use DateTimeImmutable;

trait HandlesAccess
{
    public function withValue(mixed $value) : self {
        $this->value = $value;
        return $this;
    }

    public function withMetadata(array $metadata) : self {
        $this->metadata->merge($metadata);
        return $this;
    }

    public function has(string $key) : bool {
        return $this->metadata->has($key);
    }

    public function get(string $key, mixed $default = null) : mixed {
        return $this->metadata->get($key, $default);
    }

    public function id() : string {
        return $this->id;
    }

    public function timestamp() : DateTimeImmutable {
        return $this->timestamp;
    }

    public function type() : string {
        return $this->type;
    }

    public function key() : string {
        return $this->key;
    }

    public function value() : mixed {
        return $this->value;
    }

    public function metadata() : DataMap {
        return $this->metadata;
    }

    public function toFloat() : float {
        return (float) $this->value;
    }
}