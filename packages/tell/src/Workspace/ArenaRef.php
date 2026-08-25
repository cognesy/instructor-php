<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Canonical\CanonicalHash;
use JsonException;

final readonly class ArenaRef
{
    public const int SCHEMA_VERSION = 1;

    public function __construct(
        public ?CanonicalHash $head,
    ) {}

    public static function empty(): self
    {
        return new self(null);
    }

    public static function fromBytes(string $bytes): self
    {
        try {
            $data = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ArenaIntegrityException('Tell ref is not valid JSON.', previous: $exception);
        }
        if (! is_array($data) || array_is_list($data) || array_keys($data) !== ['head', 'schema']) {
            throw new ArenaIntegrityException('Tell ref has an unsupported shape.');
        }
        if (($data['schema'] ?? null) !== self::SCHEMA_VERSION) {
            throw new ArenaIntegrityException('Tell ref has an unsupported schema version.');
        }
        if ($data['head'] !== null && ! is_string($data['head'])) {
            throw new ArenaIntegrityException('Tell ref head must be a canonical hash or null.');
        }
        try {
            $ref = new self($data['head'] === null ? null : new CanonicalHash($data['head']));
        } catch (\Throwable $exception) {
            throw new ArenaIntegrityException('Tell ref head is invalid.', previous: $exception);
        }
        if (! hash_equals($bytes, $ref->toBytes())) {
            throw new ArenaIntegrityException('Tell ref is not in the required stable representation.');
        }

        return $ref;
    }

    public function toBytes(): string
    {
        return json_encode(
            [
                'head' => $this->head?->toString(),
                'schema' => self::SCHEMA_VERSION,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        )."\n";
    }
}
