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
        ModelRecordFields::validate($data, [
            'driver', 'model', 'status', 'limits', 'modalities', 'capabilities', 'source',
        ], 'model');
        $driver = $data['driver'] ?? null;
        $model = $data['model'] ?? null;
        if (!is_string($driver) || trim($driver) === '' || !is_string($model) || trim($model) === '') {
            throw new InvalidArgumentException('Model profile requires non-empty driver and model.');
        }

        return new self(
            key: new ModelKey($driver, $model),
            status: SupportStatus::fromMixed($data['status'] ?? null),
            limits: ModelLimits::fromArray(ModelRecordFields::object($data, 'limits', 'model')),
            modalities: ModelModalities::fromArray(ModelRecordFields::object($data, 'modalities', 'model')),
            capabilities: ModelCapabilities::fromArray(ModelRecordFields::object($data, 'capabilities', 'model')),
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

    private static function string(mixed $value, string $field): string
    {
        return match (true) {
            is_string($value) => $value,
            default => throw new InvalidArgumentException("Model profile {$field} must be a string."),
        };
    }
}
