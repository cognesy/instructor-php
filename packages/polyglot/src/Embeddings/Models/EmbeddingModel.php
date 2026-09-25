<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Models;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsPricing;
use InvalidArgumentException;

final readonly class EmbeddingModel
{
    public function __construct(
        public string $driver,
        public string $model,
        public ?int $maxInputs = null,
        public ?int $maxInputTokens = null,
        public ?int $maxRequestTokens = null,
        public ?int $defaultDimensions = null,
        public ?EmbeddingsPricing $pricing = null,
        public string $catalogVersion = '',
    ) {
        self::assertIdentity($driver, 'driver');
        self::assertIdentity($model, 'model');
        self::assertPositiveOrUnknown($maxInputs, 'maxInputs');
        self::assertPositiveOrUnknown($maxInputTokens, 'maxInputTokens');
        self::assertPositiveOrUnknown($maxRequestTokens, 'maxRequestTokens');
        self::assertPositiveOrUnknown($defaultDimensions, 'defaultDimensions');

        if ($pricing !== null && (! is_finite($pricing->inputPerMToken) || $pricing->inputPerMToken < 0)) {
            throw new InvalidArgumentException('Embedding model pricing must be finite and non-negative.');
        }
    }

    public static function unknown(string $driver, string $model): self
    {
        return new self(driver: $driver, model: $model);
    }

    public static function fromArray(array $data, string $catalogVersion = ''): self
    {
        self::assertFields($data, [
            'driver',
            'model',
            'maxInputs',
            'maxInputTokens',
            'maxRequestTokens',
            'defaultDimensions',
            'pricing',
        ], 'embedding model');

        $driver = $data['driver'] ?? null;
        $model = $data['model'] ?? null;
        if (! is_string($driver) || ! is_string($model)) {
            throw new InvalidArgumentException('Embedding model requires string driver and model fields.');
        }

        return new self(
            driver: $driver,
            model: $model,
            maxInputs: self::optionalInt($data, 'maxInputs'),
            maxInputTokens: self::optionalInt($data, 'maxInputTokens'),
            maxRequestTokens: self::optionalInt($data, 'maxRequestTokens'),
            defaultDimensions: self::optionalInt($data, 'defaultDimensions'),
            pricing: self::optionalPricing($data),
            catalogVersion: $catalogVersion,
        );
    }

    public function matches(string $driver, string $model): bool
    {
        return $this->driver === $driver && $this->model === $model;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'driver' => $this->driver,
            'model' => $this->model,
            'maxInputs' => $this->maxInputs,
            'maxInputTokens' => $this->maxInputTokens,
            'maxRequestTokens' => $this->maxRequestTokens,
            'defaultDimensions' => $this->defaultDimensions,
            'pricing' => match ($this->pricing) {
                null => null,
                default => ['inputPerMToken' => $this->pricing->inputPerMToken],
            },
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @param list<string> $allowed */
    private static function assertFields(array $data, array $allowed, string $context): void
    {
        foreach (array_keys($data) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException("Unexpected {$context} field: {$field}");
            }
        }
    }

    private static function assertIdentity(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("Embedding model {$field} must be non-empty.");
        }
    }

    private static function assertPositiveOrUnknown(?int $value, string $field): void
    {
        if ($value !== null && $value <= 0) {
            throw new InvalidArgumentException("Embedding model {$field} must be positive or null.");
        }
    }

    private static function optionalInt(array $data, string $field): ?int
    {
        $value = $data[$field] ?? null;
        if ($value !== null && ! is_int($value)) {
            throw new InvalidArgumentException("Embedding model {$field} must be an integer or null.");
        }

        return $value;
    }

    private static function optionalPricing(array $data): ?EmbeddingsPricing
    {
        $pricing = $data['pricing'] ?? null;
        if ($pricing === null) {
            return null;
        }
        if (! is_array($pricing) || array_is_list($pricing)) {
            throw new InvalidArgumentException('Embedding model pricing must be an object or null.');
        }
        self::assertFields($pricing, ['inputPerMToken'], 'embedding pricing');
        if (! array_key_exists('inputPerMToken', $pricing)) {
            throw new InvalidArgumentException('Embedding model pricing requires inputPerMToken.');
        }
        $input = $pricing['inputPerMToken'];
        if (! is_int($input) && ! is_float($input)) {
            throw new InvalidArgumentException('Embedding model inputPerMToken must be numeric.');
        }

        return new EmbeddingsPricing(inputPerMToken: (float) $input);
    }
}
