<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenCode\Domain\Value;

use InvalidArgumentException;

/**
 * Value object representing an OpenCode model identifier
 *
 * OpenCode uses provider/model format (e.g., "anthropic/claude-sonnet-4-5")
 */
final readonly class ModelId
{
    public function __construct(
        public string $provider,
        public string $model,
    ) {
        if (trim($provider) === '') {
            throw new InvalidArgumentException('Provider cannot be empty');
        }
        if (trim($model) === '') {
            throw new InvalidArgumentException('Model cannot be empty');
        }
    }

    /**
     * Parse a model string in provider/model format
     */
    public static function fromString(string $value): self
    {
        $parts = explode('/', $value, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException(
                "Model must be in provider/model format, got: {$value}"
            );
        }
        return new self($parts[0], $parts[1]);
    }

    /**
     * Convert to CLI-compatible string
     */
    public function toString(): string
    {
        return "{$this->provider}/{$this->model}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
