<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Section;

trait HandlesMetadata
{
    public function withMetadata(array $metadata) : static {
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $metadata,
            messages: $this->messages,
            header: $this->header,
            footer: $this->footer,
        );
    }

    public function metadata() : array {
        return $this->metadata;
    }

    public function get(string $key, mixed $default = null) : mixed {
        return $this->metadata[$key] ?? $default;
    }

    public function set(string $key, mixed $value) : static {
        $newMetadata = $this->metadata;
        $newMetadata[$key] = $value;
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $newMetadata,
            messages: $this->messages,
            header: $this->header,
            footer: $this->footer,
        );
    }

    public function has(string $key) : bool {
        return isset($this->metadata[$key]);
    }
}