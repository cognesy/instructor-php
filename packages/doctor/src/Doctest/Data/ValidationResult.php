<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Data;

final readonly class ValidationResult
{
    /**
     * @param array<int, BlockValidation> $validBlocks
     * @param array<int, BlockValidation> $missingBlocks
     */
    public function __construct(
        public string $filePath,
        public int $totalBlocks,
        public array $validBlocks,
        public array $missingBlocks,
        public float $duration,
    ) {}

    public function hasErrors(): bool {
        return !empty($this->missingBlocks);
    }

    public function validCount(): int {
        return count($this->validBlocks);
    }

    public function missingCount(): int {
        return count($this->missingBlocks);
    }
}
