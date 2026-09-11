<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Assembly;

use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;

/**
 * Ordered provider-neutral accumulation of replay data aligned with semantic parts.
 */
final class ReplayAccumulator
{
    /** @var list<string> */
    private array $order = [];
    /** @var array<string,mixed> */
    private array $parts = [];
    private mixed $response = null;

    public function __construct(private readonly string $owner) {}

    public function remember(string $key, mixed $data = null): void
    {
        if (!array_key_exists($key, $this->parts)) {
            $this->order[] = $key;
            $this->parts[$key] = null;
        }
        if ($data !== null) {
            $this->parts[$key] = $data;
        }
    }

    public function part(string $key): mixed
    {
        return $this->parts[$key] ?? null;
    }

    public function rememberResponse(mixed $response): void
    {
        $this->response = $response;
    }

    public function envelope(): ?ReplayEnvelope
    {
        $parts = [];
        foreach ($this->order as $key) {
            $parts[] = $this->parts[$key];
        }
        return ReplayEnvelope::fromParts(
            owner: $this->owner,
            parts: $parts,
            response: $this->response,
        );
    }
}
