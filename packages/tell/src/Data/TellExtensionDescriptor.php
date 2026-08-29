<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use InvalidArgumentException;

final readonly class TellExtensionDescriptor
{
    /** @param array<string, int|float|string|bool|null> $metadata */
    public function __construct(
        public TellExtensionKind $kind,
        public string $name,
        public string $source,
        public array $metadata = [],
    ) {
        if (trim($this->name) === '' || trim($this->source) === '') {
            throw new InvalidArgumentException('Tell extension name and source must not be empty.');
        }
    }

    public function key(): string {
        return $this->kind->value . ':' . $this->name;
    }
}
