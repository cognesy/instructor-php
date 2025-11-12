<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;

final readonly class StructuredOutputResponse
{
    private InferenceResponse $inferenceResponse;

    private string $raw;
    private array $decoded;
    private mixed $deserialized;

    public function __construct(
        ?InferenceResponse $inferenceResponse = null,
        ?string $raw = null,
        ?array $decoded = null,
        mixed $deserialized = null,
    ) {
        $this->raw = $raw ?? '';
        $this->decoded = $decoded ?? [];
        $this->deserialized = $deserialized;
        $this->inferenceResponse = $inferenceResponse ?? InferenceResponse::empty();
    }

    // MUTATORS ////////////////////////////////////////////////////////

    public function with(
        ?string $raw = null,
        ?array $decoded = null,
        mixed $deserialized = null,
        ?InferenceResponse $inferenceResponse = null,
    ): self {
        return new self(
            inferenceResponse: $inferenceResponse ?? $this->inferenceResponse,
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

    public function withInferenceResponse(InferenceResponse $inferenceResponse): self {
        return $this->with(inferenceResponse: $inferenceResponse);
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

    public function inferenceResponse(): InferenceResponse {
        return $this->inferenceResponse;
    }

    // SERIALIZATION ///////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'inferenceResponse' => $this->inferenceResponse->toArray(),
            'raw' => $this->raw,
            'decoded' => $this->decoded,
            'deserialized' => $this->deserialized,
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            inferenceResponse: isset($data['inferenceResponse']) && is_array($data['inferenceResponse'])
                ? InferenceResponse::fromArray($data['inferenceResponse'])
                : null,
            raw: $data['raw'] ?? '',
            decoded: $data['decoded'] ?? [],
            deserialized: $data['deserialized'] ?? null,
        );
    }
}