<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Questions;

use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Internal\DecisionData;
use InvalidArgumentException;

final readonly class Noul
{
    private string $id;

    private ?JsonContent $instructions;

    public function __construct(
        string $id,
        string|JsonContent|null $instructions = null,
        private ?NoulCriteria $criteria = null,
    ) {
        $this->id = DecisionData::nonEmptyString($id, 'Noul question ID');
        $this->instructions = is_string($instructions) ? JsonContent::text($instructions) : $instructions;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function instructions(): ?JsonContent
    {
        return $this->instructions;
    }

    public function criteria(): ?NoulCriteria
    {
        return $this->criteria;
    }

    public function toArray(): array
    {
        $data = ['id' => $this->id, 'type' => 'noul'];
        if ($this->instructions !== null) {
            $data['instructions'] = $this->instructions->value();
        }
        if ($this->criteria !== null) {
            $data['criteria'] = $this->criteria->toArray();
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['id', 'type', 'instructions', 'criteria'], 'Noul question');
        if (($data['type'] ?? null) !== 'noul') {
            throw new InvalidArgumentException("Noul question type must be 'noul'.");
        }
        $criteria = $data['criteria'] ?? null;
        if ($criteria !== null && ! is_array($criteria)) {
            throw new InvalidArgumentException('Noul criteria must be an object or null.');
        }

        return new self(
            id: DecisionData::nonEmptyString($data['id'] ?? null, 'Noul question ID'),
            instructions: DecisionData::optionalJsonContent($data['instructions'] ?? null, 'Noul instructions'),
            criteria: $criteria === null ? null : NoulCriteria::fromArray($criteria),
        );
    }
}
