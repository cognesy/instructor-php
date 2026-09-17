<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Questions;

use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Internal\DecisionData;
use InvalidArgumentException;

final readonly class Choice
{
    private string $id;

    private ?JsonContent $instructions;

    private ChoiceOptions $options;

    public function __construct(
        string $id,
        ChoiceOptions $options,
        string|JsonContent|null $instructions = null,
    ) {
        $this->id = DecisionData::nonEmptyString($id, 'Choice question ID');
        $this->instructions = is_string($instructions) ? JsonContent::text($instructions) : $instructions;
        $this->options = $options;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function instructions(): ?JsonContent
    {
        return $this->instructions;
    }

    public function options(): ChoiceOptions
    {
        return $this->options;
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'type' => 'choice',
            'options' => $this->options->toArray(),
        ];
        if ($this->instructions !== null) {
            $data['instructions'] = $this->instructions->value();
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['id', 'type', 'instructions', 'options'], 'Choice question');
        if (($data['type'] ?? null) !== 'choice') {
            throw new InvalidArgumentException("Choice question type must be 'choice'.");
        }
        $options = $data['options'] ?? null;
        if (! is_array($options)) {
            throw new InvalidArgumentException('Choice question options must be a list.');
        }

        return new self(
            id: DecisionData::nonEmptyString($data['id'] ?? null, 'Choice question ID'),
            options: ChoiceOptions::fromArray($options),
            instructions: DecisionData::optionalJsonContent($data['instructions'] ?? null, 'Choice instructions'),
        );
    }
}
