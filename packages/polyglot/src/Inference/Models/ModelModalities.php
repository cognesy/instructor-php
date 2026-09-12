<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Models;

final readonly class ModelModalities
{
    public function __construct(
        public SupportStatus $inputText = SupportStatus::Unknown,
        public SupportStatus $inputImage = SupportStatus::Unknown,
        public SupportStatus $inputAudio = SupportStatus::Unknown,
        public SupportStatus $outputText = SupportStatus::Unknown,
    ) {}

    public static function fromArray(array $data): self {
        ModelRecordFields::validate($data, [
            'inputText', 'inputImage', 'inputAudio', 'outputText',
        ], 'modalities');
        return new self(
            inputText: SupportStatus::fromMixed($data['inputText'] ?? null),
            inputImage: SupportStatus::fromMixed($data['inputImage'] ?? null),
            inputAudio: SupportStatus::fromMixed($data['inputAudio'] ?? null),
            outputText: SupportStatus::fromMixed($data['outputText'] ?? null),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array {
        return array_filter([
            'inputText' => $this->inputText->value,
            'inputImage' => $this->inputImage->value,
            'inputAudio' => $this->inputAudio->value,
            'outputText' => $this->outputText->value,
        ], static fn (string $status): bool => $status !== SupportStatus::Unknown->value);
    }
}
