<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Collections;

use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use InvalidArgumentException;

final readonly class Questions
{
    /** @var list<Noul|Choice|Score> */
    private array $questions;

    /** @var array<string, Noul|Choice|Score> */
    private array $byId;

    private function __construct(Noul|Choice|Score ...$questions)
    {
        $byId = [];
        foreach ($questions as $question) {
            if (array_key_exists($question->id(), $byId)) {
                throw new InvalidArgumentException('Question IDs must be unique.');
            }
            $byId[$question->id()] = $question;
        }
        $this->questions = array_values($questions);
        $this->byId = $byId;
    }

    public static function of(Noul|Choice|Score ...$questions): self
    {
        return new self(...$questions);
    }

    public static function empty(): self
    {
        return new self;
    }

    public static function fromArray(array $data): self
    {
        if (! array_is_list($data)) {
            throw new InvalidArgumentException('Questions must be a list.');
        }
        $questions = array_map(static function (mixed $question): Noul|Choice|Score {
            if (! is_array($question)) {
                throw new InvalidArgumentException('Each question must be an object.');
            }

            return match ($question['type'] ?? null) {
                'noul' => Noul::fromArray($question),
                'choice' => Choice::fromArray($question),
                'score' => Score::fromArray($question),
                default => throw new InvalidArgumentException('Unknown question type.'),
            };
        }, $data);

        return new self(...$questions);
    }

    /** @return list<Noul|Choice|Score> */
    public function all(): array
    {
        return $this->questions;
    }

    public function count(): int
    {
        return count($this->questions);
    }

    public function isEmpty(): bool
    {
        return $this->questions === [];
    }

    public function question(string $id): Noul|Choice|Score
    {
        return $this->byId[$id]
            ?? throw new InvalidArgumentException("Question '{$id}' does not exist.");
    }

    public function toArray(): array
    {
        return array_map(
            static fn (Noul|Choice|Score $question): array => $question->toArray(),
            $this->questions,
        );
    }
}
