<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Section;

trait HandlesMetadata
{
    public function withMetadata(array $metadata) : static {
        $this->metadata = $metadata;
        return $this;
    }

    public function metadata() : array {
        return $this->metadata;
    }

    public function get(string $key, mixed $default = null) : mixed {
        return $this->metadata[$key] ?? $default;
    }

    public function set(string $key, mixed $value) : self {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function has(string $key) : bool {
        return isset($this->metadata[$key]);
    }
}