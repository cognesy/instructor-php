<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Models;

use Cognesy\Polyglot\Decision\Data\DecisionPricing;
use InvalidArgumentException;

final readonly class DecisionModel
{
    public DecisionCapabilities $capabilities;

    public function __construct(
        public string $driver,
        public string $model,
        public ?int $maxRequestTokens = null,
        public ?int $maxStateAndQuestionTokens = null,
        public ?DecisionPricing $pricing = null,
        public string $catalogVersion = '',
        ?DecisionCapabilities $capabilities = null,
    ) {
        self::assertIdentity($driver, 'driver');
        self::assertIdentity($model, 'model');
        self::assertPositiveOrUnknown($maxRequestTokens, 'maxRequestTokens');
        self::assertPositiveOrUnknown($maxStateAndQuestionTokens, 'maxStateAndQuestionTokens');
        $this->capabilities = $capabilities ?? DecisionCapabilities::unknown();
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
            'maxRequestTokens',
            'maxStateAndQuestionTokens',
            'pricing',
            'capabilities',
        ], 'decision model');

        $driver = $data['driver'] ?? null;
        $model = $data['model'] ?? null;
        if (! is_string($driver) || ! is_string($model)) {
            throw new InvalidArgumentException('Decision model requires string driver and model fields.');
        }

        return new self(
            driver: $driver,
            model: $model,
            maxRequestTokens: self::optionalInt($data, 'maxRequestTokens'),
            maxStateAndQuestionTokens: self::optionalInt($data, 'maxStateAndQuestionTokens'),
            pricing: self::optionalPricing($data),
            catalogVersion: $catalogVersion,
            capabilities: self::optionalCapabilities($data),
        );
    }

    public function matches(string $driver, string $model): bool
    {
        return $this->driver === $driver && $this->model === $model;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $capabilities = $this->capabilities->toArray();

        return array_filter([
            'driver' => $this->driver,
            'model' => $this->model,
            'maxRequestTokens' => $this->maxRequestTokens,
            'maxStateAndQuestionTokens' => $this->maxStateAndQuestionTokens,
            'pricing' => $this->pricing?->toArray(),
            'capabilities' => $capabilities === [] ? null : $capabilities,
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
            throw new InvalidArgumentException("Decision model {$field} must be non-empty.");
        }
    }

    private static function assertPositiveOrUnknown(?int $value, string $field): void
    {
        if ($value !== null && $value <= 0) {
            throw new InvalidArgumentException("Decision model {$field} must be positive or null.");
        }
    }

    private static function optionalInt(array $data, string $field): ?int
    {
        $value = $data[$field] ?? null;
        if ($value !== null && ! is_int($value)) {
            throw new InvalidArgumentException("Decision model {$field} must be an integer or null.");
        }

        return $value;
    }

    private static function optionalPricing(array $data): ?DecisionPricing
    {
        $pricing = $data['pricing'] ?? null;
        if ($pricing === null) {
            return null;
        }
        if (! is_array($pricing) || array_is_list($pricing)) {
            throw new InvalidArgumentException('Decision model pricing must be an object or null.');
        }

        return DecisionPricing::fromArray($pricing);
    }

    private static function optionalCapabilities(array $data): DecisionCapabilities
    {
        $capabilities = $data['capabilities'] ?? [];
        if (! is_array($capabilities) || ($capabilities !== [] && array_is_list($capabilities))) {
            throw new InvalidArgumentException('Decision model capabilities must be an object.');
        }

        return DecisionCapabilities::fromArray($capabilities);
    }
}
