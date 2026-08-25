<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

final readonly class CanonicalTextPart
{
    public function __construct(
        private string $text,
    ) {
        CanonicalInput::string($text, 'text part');
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        CanonicalInput::assertKeys($data, ['text', 'type']);
        if ($data['type'] !== 'text') {
            throw new CanonicalValidationException('Unsupported canonical content part type.');
        }

        return new self(CanonicalInput::string($data['text'], 'text part'));
    }

    public function text(): string
    {
        return $this->text;
    }

    /** @return array{text: string, type: 'text'} */
    public function toCanonicalArray(): array
    {
        return [
            'text' => $this->text,
            'type' => 'text',
        ];
    }
}
