<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

final readonly class CanonicalToolResult
{
    /** @param list<CanonicalTextPart> $parts */
    public function __construct(
        private string $callId,
        private array $parts,
        private bool $isError = false,
    ) {
        CanonicalInput::identifier($callId, 'tool result call id');
        foreach ($parts as $part) {
            if (! $part instanceof CanonicalTextPart) {
                throw new CanonicalValidationException('Canonical tool result parts must be text parts.');
            }
        }
        if ($parts === []) {
            throw new CanonicalValidationException('Canonical tool results must contain at least one text part.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        CanonicalInput::assertKeys($data, ['callId', 'isError', 'parts']);
        if (! is_bool($data['isError'])) {
            throw new CanonicalValidationException('Canonical tool result error state must be boolean.');
        }
        $parts = array_map(
            static fn (mixed $part): CanonicalTextPart => CanonicalTextPart::fromArray(CanonicalInput::map($part, 'tool result part')),
            CanonicalInput::list($data['parts'], 'tool result parts'),
        );

        return new self(
            CanonicalInput::identifier($data['callId'], 'tool result call id'),
            $parts,
            $data['isError'],
        );
    }

    public function callId(): string
    {
        return $this->callId;
    }

    /** @return list<CanonicalTextPart> */
    public function parts(): array
    {
        return $this->parts;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    /** @return array{callId: string, isError: bool, parts: list<array{text: string, type: 'text'}>} */
    public function toCanonicalArray(): array
    {
        return [
            'callId' => $this->callId,
            'isError' => $this->isError,
            'parts' => array_map(static fn (CanonicalTextPart $part): array => $part->toCanonicalArray(), $this->parts),
        ];
    }
}
