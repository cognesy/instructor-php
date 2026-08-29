<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena\Record;

use Cognesy\Tell\Workspace\Arena\RecordException;

final readonly class ToolResult
{
    /** @param list<TextPart> $parts */
    public function __construct(
        private string $callId,
        private array $parts,
        private bool $isError = false,
    ) {
        Input::identifier($callId, 'tool result call id');
        foreach ($parts as $part) {
            if (!$part instanceof TextPart) {
                throw new RecordException('Arena record tool result parts must be text parts.');
            }
        }
        if ($parts === []) {
            throw new RecordException('Arena record tool results must contain at least one text part.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        Input::assertKeys($data, ['callId', 'isError', 'parts']);
        if (!is_bool($data['isError'])) {
            throw new RecordException('Arena record tool result error state must be boolean.');
        }
        $parts = array_map(
            static fn (mixed $part): TextPart => TextPart::fromArray(Input::map($part, 'tool result part')),
            Input::list($data['parts'], 'tool result parts'),
        );

        return new self(
            Input::identifier($data['callId'], 'tool result call id'),
            $parts,
            $data['isError'],
        );
    }

    public function callId(): string {
        return $this->callId;
    }

    /** @return list<TextPart> */
    public function parts(): array {
        return $this->parts;
    }

    public function isError(): bool {
        return $this->isError;
    }

    /** @return array{callId: string, isError: bool, parts: list<array{text: string, type: 'text'}>} */
    public function toArray(): array {
        return [
            'callId' => $this->callId,
            'isError' => $this->isError,
            'parts' => array_map(static fn (TextPart $part): array => $part->toArray(), $this->parts),
        ];
    }
}
