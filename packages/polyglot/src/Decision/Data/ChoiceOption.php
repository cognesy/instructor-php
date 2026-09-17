<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Data;

use Cognesy\Polyglot\Decision\Internal\DecisionData;

final readonly class ChoiceOption
{
    private string $id;

    private ?JsonContent $description;

    public function __construct(string $id, string|JsonContent|null $description = null)
    {
        $this->id = DecisionData::nonEmptyString($id, 'Choice option ID');
        $this->description = is_string($description) ? JsonContent::text($description) : $description;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function description(): ?JsonContent
    {
        return $this->description;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description?->value(),
        ];
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['id', 'description'], 'Choice option');

        return new self(
            id: DecisionData::nonEmptyString($data['id'] ?? null, 'Choice option ID'),
            description: DecisionData::optionalJsonContent(
                $data['description'] ?? null,
                'Choice option description',
            ),
        );
    }
}
