<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

/**
 * Stable compatibility metadata for a named Tell session.
 *
 * This closed shape deliberately contains no provider, filesystem, credential,
 * or execution data. It is attached only to conversation roots selected through
 * the workspace session compatibility namespace.
 */
final readonly class CanonicalSessionMetadata
{
    public const int MIGRATION_VERSION = 1;

    public function __construct(
        private string $name,
        private ?CanonicalHash $sourceFingerprint = null,
        private int $migrationVersion = self::MIGRATION_VERSION,
    ) {
        CanonicalInput::identifier($name, 'session name');
        if ($migrationVersion !== self::MIGRATION_VERSION) {
            throw new CanonicalValidationException('Canonical session migration version is unsupported.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        CanonicalInput::assertKeys($data, ['migrationVersion', 'name', 'sourceFingerprint']);
        if (! is_int($data['migrationVersion'])) {
            throw new CanonicalValidationException('Canonical session migration version must be an integer.');
        }
        if ($data['sourceFingerprint'] !== null && ! is_string($data['sourceFingerprint'])) {
            throw new CanonicalValidationException('Canonical session source fingerprint must be a SHA-256 hash or null.');
        }

        return new self(
            CanonicalInput::identifier($data['name'], 'session name'),
            $data['sourceFingerprint'] === null
                ? null
                : CanonicalInput::hash($data['sourceFingerprint'], 'session source fingerprint'),
            $data['migrationVersion'],
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function sourceFingerprint(): ?CanonicalHash
    {
        return $this->sourceFingerprint;
    }

    public function migrationVersion(): int
    {
        return $this->migrationVersion;
    }

    /** @return array{migrationVersion: int, name: string, sourceFingerprint: string|null} */
    public function toCanonicalArray(): array
    {
        return [
            'migrationVersion' => $this->migrationVersion,
            'name' => $this->name,
            'sourceFingerprint' => $this->sourceFingerprint?->toString(),
        ];
    }
}
