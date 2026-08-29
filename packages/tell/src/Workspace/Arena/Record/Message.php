<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena\Record;

use Cognesy\Tell\Workspace\Arena\RecordException;
use ValueError;

final readonly class Message
{
    /** @param list<TextPart> $parts */
    public function __construct(
        private Role $role,
        private array $parts,
    ) {
        foreach ($parts as $part) {
            if (!$part instanceof TextPart) {
                throw new RecordException('Arena record message parts must be text parts.');
            }
        }
        if ($role !== Role::Assistant && $parts === []) {
            throw new RecordException('Only assistant messages may omit text parts.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        Input::assertKeys($data, ['parts', 'role']);
        if (!is_string($data['role'])) {
            throw new RecordException('Arena record message role must be supported.');
        }
        try {
            $role = Role::from($data['role']);
        } catch (ValueError) {
            throw new RecordException('Arena record message role must be supported.');
        }

        $parts = array_map(
            static fn (mixed $part): TextPart => TextPart::fromArray(Input::map($part, 'message part')),
            Input::list($data['parts'], 'message parts'),
        );

        return new self($role, $parts);
    }

    public function role(): Role {
        return $this->role;
    }

    /** @return list<TextPart> */
    public function parts(): array {
        return $this->parts;
    }

    /** @return array{parts: list<array{text: string, type: 'text'}>, role: string} */
    public function toArray(): array {
        return [
            'parts' => array_map(static fn (TextPart $part): array => $part->toArray(), $this->parts),
            'role' => $this->role->value,
        ];
    }
}
