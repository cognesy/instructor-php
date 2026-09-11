<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Models;

final readonly class ModelModalities
{
    public function __construct(
        public SupportStatus $inputText = SupportStatus::Unknown,
        public SupportStatus $inputImage = SupportStatus::Unknown,
        public SupportStatus $inputAudio = SupportStatus::Unknown,
        public SupportStatus $inputFile = SupportStatus::Unknown,
        public SupportStatus $outputText = SupportStatus::Unknown,
    ) {}

    public static function fromArray(array $data): self {
        return new self(
            inputText: SupportStatus::fromMixed($data['inputText'] ?? null),
            inputImage: SupportStatus::fromMixed($data['inputImage'] ?? null),
            inputAudio: SupportStatus::fromMixed($data['inputAudio'] ?? null),
            inputFile: SupportStatus::fromMixed($data['inputFile'] ?? null),
            outputText: SupportStatus::fromMixed($data['outputText'] ?? null),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array {
        return array_filter([
            'inputText' => $this->inputText->value,
            'inputImage' => $this->inputImage->value,
            'inputAudio' => $this->inputAudio->value,
            'inputFile' => $this->inputFile->value,
            'outputText' => $this->outputText->value,
        ], static fn (string $status): bool => $status !== SupportStatus::Unknown->value);
    }
}
