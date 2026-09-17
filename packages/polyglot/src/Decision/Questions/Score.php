<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Questions;

use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Internal\DecisionData;
use InvalidArgumentException;

final readonly class Score
{
    private string $id;

    private ?JsonContent $instructions;

    private ScoreLevels $levels;

    public function __construct(
        string $id,
        ScoreLevels $levels,
        string|JsonContent|null $instructions = null,
    ) {
        $this->id = DecisionData::nonEmptyString($id, 'Score question ID');
        $this->instructions = is_string($instructions) ? JsonContent::text($instructions) : $instructions;
        $this->levels = $levels;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function instructions(): ?JsonContent
    {
        return $this->instructions;
    }

    public function levels(): ScoreLevels
    {
        return $this->levels;
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'type' => 'score',
            'levels' => $this->levels->toArray(),
        ];
        if ($this->instructions !== null) {
            $data['instructions'] = $this->instructions->value();
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['id', 'type', 'instructions', 'levels'], 'Score question');
        if (($data['type'] ?? null) !== 'score') {
            throw new InvalidArgumentException("Score question type must be 'score'.");
        }
        $levels = $data['levels'] ?? null;
        if (! is_array($levels)) {
            throw new InvalidArgumentException('Score question levels must be a list.');
        }

        return new self(
            id: DecisionData::nonEmptyString($data['id'] ?? null, 'Score question ID'),
            levels: ScoreLevels::fromArray($levels),
            instructions: DecisionData::optionalJsonContent($data['instructions'] ?? null, 'Score instructions'),
        );
    }
}
