<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Models;

use InvalidArgumentException;

final readonly class ModelProfile
{
    public function __construct(
        public ModelKey $key,
        public SupportStatus $status = SupportStatus::Unknown,
        public ModelLimits $limits = new ModelLimits,
        public ModelModalities $modalities = new ModelModalities,
        public ModelCapabilities $capabilities = new ModelCapabilities,
        public string $source = 'unknown',
        public string $catalogVersion = '',
    ) {}

    public static function unknown(ModelKey $key, string $catalogVersion = ''): self
    {
        return new self(key: $key, catalogVersion: $catalogVersion);
    }

    public static function fromArray(array $data, string $catalogVersion = ''): self
    {
        $driver = $data['driver'] ?? null;
        $model = $data['model'] ?? null;
        if (!is_string($driver) || $driver === '' || !is_string($model) || $model === '') {
            throw new InvalidArgumentException('Model profile requires non-empty driver and model.');
        }

        return new self(
            key: new ModelKey($driver, $model),
            status: SupportStatus::fromMixed($data['status'] ?? null),
            limits: ModelLimits::fromArray(self::nested($data, 'limits')),
            modalities: ModelModalities::fromArray(self::nested($data, 'modalities')),
            capabilities: ModelCapabilities::fromArray(self::nested($data, 'capabilities')),
            source: self::string($data['source'] ?? 'unknown', 'source'),
            catalogVersion: $catalogVersion,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $limits = $this->limits->toArray();
        $modalities = $this->modalities->toArray();
        $capabilities = $this->capabilities->toArray();

        return [
            ...$this->key->toArray(),
            ...match ($this->status) {
                SupportStatus::Unknown => [],
                default => ['status' => $this->status->value],
            },
            ...self::section('limits', $limits),
            ...self::section('modalities', $modalities),
            ...self::section('capabilities', $capabilities),
            ...match ($this->source) {
                'unknown' => [],
                default => ['source' => $this->source],
            },
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private static function section(string $name, array $values): array
    {
        return match ($values) {
            [] => [],
            default => [$name => $values],
        };
    }

    private static function nested(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (!is_array($value)) {
            throw new InvalidArgumentException("Model profile {$key} must be an object.");
        }

        return $value;
    }

    private static function string(mixed $value, string $field): string
    {
        return match (true) {
            is_string($value) => $value,
            default => throw new InvalidArgumentException("Model profile {$field} must be a string."),
        };
    }
}
