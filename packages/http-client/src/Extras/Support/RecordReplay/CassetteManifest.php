<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteCorruptException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\UnsupportedCassetteVersionException;

final readonly class CassetteManifest
{
    public const SCHEMA = 'instructor-http-cassette';
    public const VERSION = 1;

    public function __construct(
        public string $schema = self::SCHEMA,
        public int $version = self::VERSION,
        public string $fingerprintVersion = 'custom-v1',
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $schema = $data['schema'] ?? null;
        $version = $data['version'] ?? null;
        $fingerprintVersion = $data['fingerprintVersion'] ?? null;
        if ($schema !== self::SCHEMA || !is_int($version) || !is_string($fingerprintVersion) || $fingerprintVersion === '') {
            throw new CassetteCorruptException('HTTP cassette manifest is malformed.');
        }
        if ($version !== self::VERSION) {
            throw new UnsupportedCassetteVersionException('HTTP cassette manifest version is unsupported.');
        }

        return new self($schema, $version, $fingerprintVersion);
    }

    /** @return array{schema: string, version: int, fingerprintVersion: string} */
    public function toArray(): array
    {
        return [
            'schema' => $this->schema,
            'version' => $this->version,
            'fingerprintVersion' => $this->fingerprintVersion,
        ];
    }
}
