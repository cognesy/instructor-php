<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use InvalidArgumentException;

final readonly class BatchCursor
{
    public function __construct(
        private string $token,
        private string $provider,
        private string $scope,
        private int $limit,
    ) {
        if ($token === '' || $provider === '' || $scope === '' || $limit < 1) {
            throw new InvalidArgumentException('Invalid batch cursor.');
        }
    }

    public function token(): string
    {
        return $this->token;
    }
    public function provider(): string
    {
        return $this->provider;
    }
    public function scope(): string
    {
        return $this->scope;
    }
    public function limit(): int
    {
        return $this->limit;
    }

    public function assertMatches(string $provider, string $scope, int $limit): void
    {
        if ($this->provider !== $provider || $this->scope !== $scope || $this->limit !== $limit) {
            throw new InvalidArgumentException('Batch cursor belongs to a different listing query.');
        }
    }

    /** @return array{token: string, provider: string, scope: string, limit: int} */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'provider' => $this->provider,
            'scope' => $this->scope,
            'limit' => $this->limit,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (!is_string($data['token'] ?? null)
            || !is_string($data['provider'] ?? null)
            || !is_string($data['scope'] ?? null)
            || !is_int($data['limit'] ?? null)) {
            throw new InvalidArgumentException('Invalid serialized batch cursor.');
        }

        return new self($data['token'], $data['provider'], $data['scope'], $data['limit']);
    }
}
