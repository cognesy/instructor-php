<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Data;

final readonly class BlockValidation
{
    public function __construct(
        public ?string $id,
        public string $language,
        public string $expectedPath,
        public string $sourcePath,
        public ?int $lineNumber,
        public bool $exists,
    ) {}
}

