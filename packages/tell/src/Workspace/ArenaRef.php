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
        public ?BranchProvenance $provenance = null,
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
        if (! is_array($data) || array_is_list($data) || ! in_array(array_keys($data), [
            ['head', 'schema'],
            ['head', 'provenance', 'schema'],
        ], true)) {
            throw new ArenaIntegrityException('Tell ref has an unsupported shape.');
        }
        if (($data['schema'] ?? null) !== self::SCHEMA_VERSION) {
            throw new ArenaIntegrityException('Tell ref has an unsupported schema version.');
        }
        if ($data['head'] !== null && ! is_string($data['head'])) {
            throw new ArenaIntegrityException('Tell ref head must be a canonical hash or null.');
        }
        try {
            $ref = new self(
                $data['head'] === null ? null : new CanonicalHash($data['head']),
                self::provenanceFromData($data['provenance'] ?? null),
            );
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
        $data = ['head' => $this->head?->toString()];
        if ($this->provenance !== null) {
            $data['provenance'] = $this->provenance->toArray();
        }
        $data['schema'] = self::SCHEMA_VERSION;

        return json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        )."\n";
    }

    /** @param mixed $data */
    private static function provenanceFromData(mixed $data): ?BranchProvenance
    {
        if ($data === null) {
            return null;
        }
        if (! is_array($data) || array_is_list($data) || ! in_array(array_keys($data), [
            ['source', 'branch', 'head'],
            ['source', 'branch', 'head', 'metadata'],
        ], true)) {
            throw new ArenaIntegrityException('Tell ref provenance has an unsupported shape.');
        }
        if (! in_array($data['source'] ?? null, ['branch', 'current', 'empty', 'agent'], true)) {
            throw new ArenaIntegrityException('Tell ref provenance source is invalid.');
        }
        if ($data['branch'] !== null && ! is_string($data['branch'])) {
            throw new ArenaIntegrityException('Tell ref provenance branch is invalid.');
        }
        if ($data['source'] === 'empty' && ($data['branch'] !== null || $data['head'] !== null)) {
            throw new ArenaIntegrityException('Tell empty branch provenance is invalid.');
        }
        if ($data['source'] !== 'empty' && (! is_string($data['branch']) || $data['head'] !== null && ! is_string($data['head']))) {
            throw new ArenaIntegrityException('Tell ref provenance source details are invalid.');
        }
        if ($data['branch'] !== null && $data['branch'] !== 'main') {
            BranchName::fromStored($data['branch']);
        }
        $metadata = $data['metadata'] ?? [];
        if (! is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
            throw new ArenaIntegrityException('Tell ref provenance metadata is invalid.');
        }
        if ($data['source'] === 'agent') {
            self::assertAgentProvenance($metadata);
        } elseif ($metadata !== []) {
            throw new ArenaIntegrityException('Tell ref provenance metadata is unsupported.');
        }

        return new BranchProvenance(
            $data['source'],
            $data['branch'],
            $data['head'] === null ? null : new CanonicalHash($data['head']),
            $metadata,
        );
    }

    /** @param array<string, mixed> $metadata */
    private static function assertAgentProvenance(array $metadata): void
    {
        if (array_keys($metadata) !== ['kind', 'context', 'definition', 'executionId', 'configuration']) {
            throw new ArenaIntegrityException('Tell delegated child provenance has an unsupported shape.');
        }
        if ($metadata['kind'] !== 'delegated-child' || ! in_array($metadata['context'], ['fork', 'fresh'], true)) {
            throw new ArenaIntegrityException('Tell delegated child provenance is invalid.');
        }
        if (! is_string($metadata['definition']) || $metadata['definition'] === '' || ! is_string($metadata['executionId']) || $metadata['executionId'] === '') {
            throw new ArenaIntegrityException('Tell delegated child provenance identifiers are invalid.');
        }
        if (! is_array($metadata['configuration']) || array_is_list($metadata['configuration']) || array_keys($metadata['configuration']) !== ['policy']) {
            throw new ArenaIntegrityException('Tell delegated child configuration provenance is invalid.');
        }
        $policy = $metadata['configuration']['policy'];
        if (! is_array($policy) || array_is_list($policy)) {
            throw new ArenaIntegrityException('Tell delegated child policy provenance is invalid.');
        }
        foreach ($policy as $source) {
            if (! in_array($source, ['cli', 'branch', 'project', 'user', 'bundled'], true)) {
                throw new ArenaIntegrityException('Tell delegated child policy provenance source is invalid.');
            }
        }
    }
}
