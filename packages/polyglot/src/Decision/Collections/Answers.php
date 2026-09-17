<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Collections;

use Cognesy\Polyglot\Decision\Answers\ChoiceAnswer;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Answers\ScoreAnswer;
use InvalidArgumentException;

final readonly class Answers
{
    /** @var list<NoulAnswer|ChoiceAnswer|ScoreAnswer> */
    private array $answers;

    /** @var array<string, NoulAnswer|ChoiceAnswer|ScoreAnswer> */
    private array $byId;

    private function __construct(NoulAnswer|ChoiceAnswer|ScoreAnswer ...$answers)
    {
        $byId = [];
        foreach ($answers as $answer) {
            $key = self::key($answer->questionId());
            if (array_key_exists($key, $byId)) {
                throw new InvalidArgumentException('Answer question IDs must be unique.');
            }
            $byId[$key] = $answer;
        }
        $this->answers = array_values($answers);
        $this->byId = $byId;
    }

    public static function of(NoulAnswer|ChoiceAnswer|ScoreAnswer ...$answers): self
    {
        return new self(...$answers);
    }

    public static function fromArray(array $data): self
    {
        if (! array_is_list($data)) {
            throw new InvalidArgumentException('Answers must be a list.');
        }
        $answers = array_map(static function (mixed $answer): NoulAnswer|ChoiceAnswer|ScoreAnswer {
            if (! is_array($answer)) {
                throw new InvalidArgumentException('Each answer must be an object.');
            }

            return match ($answer['type'] ?? null) {
                'noul' => NoulAnswer::fromArray($answer),
                'choice' => ChoiceAnswer::fromArray($answer),
                'score' => ScoreAnswer::fromArray($answer),
                default => throw new InvalidArgumentException('Unknown answer type.'),
            };
        }, $data);

        return new self(...$answers);
    }

    /** @return list<NoulAnswer|ChoiceAnswer|ScoreAnswer> */
    public function all(): array
    {
        return $this->answers;
    }

    public function count(): int
    {
        return count($this->answers);
    }

    public function answer(string $questionId): NoulAnswer|ChoiceAnswer|ScoreAnswer
    {
        return $this->byId[self::key($questionId)]
            ?? throw new InvalidArgumentException("Answer for question '{$questionId}' does not exist.");
    }

    public function noul(string $questionId): NoulAnswer
    {
        $answer = $this->answer($questionId);
        if (! $answer instanceof NoulAnswer) {
            throw new InvalidArgumentException("Answer for question '{$questionId}' is not a Noul answer.");
        }

        return $answer;
    }

    public function choice(string $questionId): ChoiceAnswer
    {
        $answer = $this->answer($questionId);
        if (! $answer instanceof ChoiceAnswer) {
            throw new InvalidArgumentException("Answer for question '{$questionId}' is not a Choice answer.");
        }

        return $answer;
    }

    public function score(string $questionId): ScoreAnswer
    {
        $answer = $this->answer($questionId);
        if (! $answer instanceof ScoreAnswer) {
            throw new InvalidArgumentException("Answer for question '{$questionId}' is not a Score answer.");
        }

        return $answer;
    }

    public function toArray(): array
    {
        return array_map(
            static fn (NoulAnswer|ChoiceAnswer|ScoreAnswer $answer): array => $answer->toArray(),
            $this->answers,
        );
    }

    private static function key(string $id): string
    {
        return '#'.$id;
    }
}
