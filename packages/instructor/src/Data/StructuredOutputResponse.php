<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

final readonly class StructuredOutputResponse
{
    private string $raw;
    private array $decoded;
    private mixed $deserialized;

    public function __construct(
        ?string $raw = null,
        ?array $decoded = null,
        mixed $deserialized = null,
    ) {
        $this->raw = $raw ?? '';
        $this->decoded = $decoded ?? [];
        $this->deserialized = $deserialized;
    }

    // MUTATORS ////////////////////////////////////////////////////////

    public function with(
        ?string $raw = null,
        ?array $decoded = null,
        mixed $deserialized = null,
    ): self {
        return new self(
            raw: $raw ?? $this->raw,
            decoded: $decoded ?? $this->decoded,
            deserialized: $deserialized ?? $this->deserialized,
        );
    }

    public function withRaw(string $raw): self {
        return $this->with(raw: $raw);
    }

    public function withDecoded(array $decoded): self {
        return $this->with(decoded: $decoded);
    }

    public function withDeserialized(mixed $deserialized): self {
        return $this->with(deserialized: $deserialized);
    }

    // ACCESSORS ///////////////////////////////////////////////////////

    public function raw(): string {
        return $this->raw;
    }

    public function decoded(): array {
        return $this->decoded;
    }

    public function deserialized(): mixed {
        return $this->deserialized;
    }

    // SERIALIZATION ///////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'raw' => $this->raw,
            'decoded' => $this->decoded,
            'deserialized' => $this->deserialized, // TODO: maybe this should not be deserialized?
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            raw: $data['raw'] ?? '',
            decoded: $data['decoded'] ?? [],
            deserialized: $data['deserialized'] ?? null, // TODO: maybe this should be deserialized via main SO flow?
        );
    }
}