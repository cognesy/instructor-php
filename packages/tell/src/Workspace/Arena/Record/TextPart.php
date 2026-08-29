<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena\Record;

use Cognesy\Tell\Workspace\Arena\RecordException;

final readonly class TextPart
{
    public function __construct(
        private string $text,
    ) {
        Input::string($text, 'text part');
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        Input::assertKeys($data, ['text', 'type']);
        if ($data['type'] !== 'text') {
            throw new RecordException('Unsupported arena record content part type.');
        }

        return new self(Input::string($data['text'], 'text part'));
    }

    public function text(): string {
        return $this->text;
    }

    /** @return array{text: string, type: 'text'} */
    public function toArray(): array {
        return [
            'text' => $this->text,
            'type' => 'text',
        ];
    }
}
