<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Filter;

use Override;

final readonly class MetadataEquals implements MetadataFilter
{
    public function __construct(
        public string $field,
        public bool|float|int|string|null $value,
    ) {}

    #[Override]
    public function matches(array $metadata): bool
    {
        return array_key_exists($this->field, $metadata)
            && $metadata[$this->field] === $this->value;
    }
}
