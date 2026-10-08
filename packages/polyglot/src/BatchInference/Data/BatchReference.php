<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use InvalidArgumentException;

final readonly class BatchReference
{
    public function __construct(
        private BatchJobId $id,
        private string $provider,
        private string $scope,
        private string $route,
        private string $codec,
        private ?int $expectedCount = null,
        private bool $inputClosed = false,
        private ?BatchManifest $manifest = null,
    ) {
        if ($provider === '' || $scope === '' || $route === '' || $codec === '') {
            throw new InvalidArgumentException('Batch reference provider, scope, route, and codec are required.');
        }
        if ($expectedCount !== null && $expectedCount < 0) {
            throw new InvalidArgumentException('Expected item count cannot be negative.');
        }
        if ($manifest !== null && $expectedCount !== null && $manifest->count() !== $expectedCount) {
            throw new InvalidArgumentException('Batch manifest count differs from the expected item count.');
        }
    }

    public function id(): BatchJobId
    {
        return $this->id;
    }
    public function provider(): string
    {
        return $this->provider;
    }
    public function scope(): string
    {
        return $this->scope;
    }
    public function route(): string
    {
        return $this->route;
    }
    public function codec(): string
    {
        return $this->codec;
    }
    public function expectedCount(): ?int
    {
        return $this->expectedCount;
    }
    public function inputClosed(): bool
    {
        return $this->inputClosed;
    }
    public function manifest(): ?BatchManifest
    {
        return $this->manifest;
    }

    /** @return array{id: string, provider: string, scope: string, route: string, codec: string, expectedCount: ?int, inputClosed: bool, manifest: ?list<string>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'provider' => $this->provider,
            'scope' => $this->scope,
            'route' => $this->route,
            'codec' => $this->codec,
            'expectedCount' => $this->expectedCount,
            'inputClosed' => $this->inputClosed,
            'manifest' => $this->manifest?->toArray(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (!is_string($data['id'] ?? null)
            || !is_string($data['provider'] ?? null)
            || !is_string($data['scope'] ?? null)
            || !is_string($data['route'] ?? null)
            || !is_string($data['codec'] ?? null)
            || !array_key_exists('inputClosed', $data)
            || !is_bool($data['inputClosed'])
            || (isset($data['expectedCount']) && !is_int($data['expectedCount']))
            || (isset($data['manifest']) && !is_array($data['manifest']))) {
            throw new InvalidArgumentException('Invalid serialized batch reference.');
        }

        return new self(
            id: new BatchJobId($data['id']),
            provider: $data['provider'],
            scope: $data['scope'],
            route: $data['route'],
            codec: $data['codec'],
            expectedCount: $data['expectedCount'] ?? null,
            inputClosed: $data['inputClosed'],
            manifest: isset($data['manifest']) ? BatchManifest::fromArray($data['manifest']) : null,
        );
    }
}
