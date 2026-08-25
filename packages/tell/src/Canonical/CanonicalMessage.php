<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

final readonly class CanonicalMessage
{
    /** @param list<CanonicalTextPart> $parts */
    public function __construct(
        private CanonicalRole $role,
        private array $parts,
    ) {
        foreach ($parts as $part) {
            if (! $part instanceof CanonicalTextPart) {
                throw new CanonicalValidationException('Canonical message parts must be text parts.');
            }
        }
        if ($role !== CanonicalRole::Assistant && $parts === []) {
            throw new CanonicalValidationException('Only assistant messages may omit text parts.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        CanonicalInput::assertKeys($data, ['parts', 'role']);
        if (! is_string($data['role'])) {
            throw new CanonicalValidationException('Canonical message role must be a supported role.');
        }
        try {
            $role = CanonicalRole::from($data['role']);
        } catch (\ValueError) {
            throw new CanonicalValidationException('Canonical message role must be a supported role.');
        }

        $parts = array_map(
            static fn (mixed $part): CanonicalTextPart => CanonicalTextPart::fromArray(CanonicalInput::map($part, 'message part')),
            CanonicalInput::list($data['parts'], 'message parts'),
        );

        return new self($role, $parts);
    }

    public function role(): CanonicalRole
    {
        return $this->role;
    }

    /** @return list<CanonicalTextPart> */
    public function parts(): array
    {
        return $this->parts;
    }

    /** @return array{parts: list<array{text: string, type: 'text'}>, role: string} */
    public function toCanonicalArray(): array
    {
        return [
            'parts' => array_map(static fn (CanonicalTextPart $part): array => $part->toCanonicalArray(), $this->parts),
            'role' => $this->role->value,
        ];
    }
}
