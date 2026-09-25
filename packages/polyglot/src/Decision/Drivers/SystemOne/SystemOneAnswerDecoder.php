<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\SystemOne;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Answers\AnswerSignals;
use Cognesy\Polyglot\Decision\Answers\ChoiceAnswer;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Answers\ScoreAnswer;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\ChoiceProbabilities;
use Cognesy\Polyglot\Decision\Collections\ScoreLegend;
use Cognesy\Polyglot\Decision\Collections\ScoreProbabilities;
use Cognesy\Polyglot\Decision\Data\DecisionProviderRequestId;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use stdClass;

/** @internal */
final readonly class SystemOneAnswerDecoder
{
    public function __construct(
        private string $provider,
        private float $probabilitySumTolerance,
        private float $scoreValueTolerance,
        private float $choiceWinnerTolerance = 0.000000001,
    ) {}

    /** @param array<string, AnswerSignals> $signals */
    public function decode(
        stdClass $wireAnswers,
        DecisionRequest $request,
        string $model,
        DecisionUsage $usage,
        HttpResponse $responseData,
        DecisionProviderRequestId $providerRequestId,
        array $signals = [],
    ): DecisionResponse {
        $this->assertSameKeys(
            expected: array_map(
                static fn (Noul|Choice|Score $question): string => $question->id(),
                $request->questions()->all(),
            ),
            actual: $this->objectKeys($wireAnswers),
            context: "{$this->provider} answer IDs",
        );

        $answers = [];
        foreach ($request->questions()->all() as $question) {
            $wireAnswer = $wireAnswers->{$question->id()} ?? null;
            if (! $wireAnswer instanceof stdClass) {
                throw new DecisionResponseException(
                    "{$this->provider} answer '{$question->id()}' must be an object.",
                );
            }
            $answers[] = $this->answer(
                $question,
                $wireAnswer,
                $signals[$question->id()] ?? AnswerSignals::empty(),
            );
        }

        return new DecisionResponse(
            answers: Answers::of(...$answers),
            model: $model,
            usage: $usage,
            responseData: $responseData,
            providerRequestId: $providerRequestId,
        );
    }

    private function answer(
        Noul|Choice|Score $question,
        stdClass $answer,
        AnswerSignals $signals,
    ): NoulAnswer|ChoiceAnswer|ScoreAnswer {
        $type = $this->requiredString(
            $answer,
            'type',
            "{$this->provider} answer '{$question->id()}' type",
        );

        return match (true) {
            $question instanceof Noul && $type === 'noul' => $this->noulAnswer($question, $answer, $signals),
            $question instanceof Choice && $type === 'choice' => $this->choiceAnswer($question, $answer, $signals),
            $question instanceof Score && $type === 'score' => $this->scoreAnswer($question, $answer, $signals),
            default => throw new DecisionResponseException(
                "{$this->provider} answer '{$question->id()}' type does not match its question.",
            ),
        };
    }

    private function noulAnswer(Noul $question, stdClass $answer, AnswerSignals $signals): NoulAnswer
    {
        $context = "{$this->provider} Noul answer '{$question->id()}'";
        $this->assertExactFields($answer, ['type', 'noul'], $context);

        return new NoulAnswer(
            questionId: $question->id(),
            probability: $this->probability($answer->noul ?? null, $context),
            signals: $signals,
        );
    }

    private function choiceAnswer(Choice $question, stdClass $answer, AnswerSignals $signals): ChoiceAnswer
    {
        $context = "{$this->provider} Choice answer '{$question->id()}'";
        $this->assertExactFields($answer, ['type', 'choice', 'confidence', 'probabilities'], $context);
        $choice = $this->requiredString($answer, 'choice', "{$context} choice");
        if (! $question->options()->has($choice)) {
            throw new DecisionResponseException("{$context} selected an unknown option.");
        }
        $wireProbabilities = $this->requiredObject($answer, 'probabilities', "{$context} probabilities");
        $optionIds = array_map(static fn ($option): string => $option->id(), $question->options()->all());
        $this->assertSameKeys(
            $optionIds,
            $this->objectKeys($wireProbabilities),
            "{$context} probability keys",
        );
        $entries = [];
        foreach ($optionIds as $optionId) {
            $entries[] = [
                $optionId,
                $this->probability($wireProbabilities->{$optionId} ?? null, "{$context} probability"),
            ];
        }
        $this->assertProbabilitySum(array_column($entries, 1), $context);
        if ($entries === []) {
            throw new DecisionResponseException("{$context} has no probabilities.");
        }
        $probabilitiesById = array_column($entries, 1, 0);
        if ($probabilitiesById[$choice] < max($probabilitiesById) - $this->choiceWinnerTolerance) {
            throw new DecisionResponseException("{$context} choice must have the highest probability.");
        }

        return new ChoiceAnswer(
            questionId: $question->id(),
            value: $choice,
            confidence: $this->probability($answer->confidence ?? null, "{$context} confidence"),
            probabilities: ChoiceProbabilities::of(...$entries),
            signals: $signals,
        );
    }

    private function scoreAnswer(Score $question, stdClass $answer, AnswerSignals $signals): ScoreAnswer
    {
        $context = "{$this->provider} Score answer '{$question->id()}'";
        $this->assertExactFields(
            $answer,
            ['type', 'score', 'confidence', 'probabilities', 'legend'],
            $context,
        );
        $wireProbabilities = $this->requiredObject($answer, 'probabilities', "{$context} probabilities");
        $wireLegend = $this->requiredObject($answer, 'legend', "{$context} legend");
        $levelKeys = array_map('strval', range(0, $question->levels()->count() - 1));
        $this->assertSameKeys($levelKeys, $this->objectKeys($wireProbabilities), "{$context} probability keys");
        $this->assertSameKeys($levelKeys, $this->objectKeys($wireLegend), "{$context} legend keys");

        $probabilities = [];
        $legend = [];
        foreach ($levelKeys as $level => $key) {
            $probabilities[] = $this->probability(
                $wireProbabilities->{$key} ?? null,
                "{$context} probability",
            );
            $wireDescription = $wireLegend->{$key} ?? null;
            if (! is_string($wireDescription)
                && ! is_array($wireDescription)
                && ! $wireDescription instanceof stdClass
            ) {
                throw new DecisionResponseException(
                    "{$context} legend entry must be text, an object, or a list.",
                );
            }
            $description = JsonContent::from($wireDescription);
            if (! $this->sameJson($description->value(), $question->levels()->at($level)->value())) {
                throw new DecisionResponseException("{$context} legend does not match its question.");
            }
            $legend[] = $wireDescription;
        }
        $this->assertProbabilitySum($probabilities, $context);
        $score = $this->finiteNumber($answer->score ?? null, "{$context} score");
        $expected = array_sum(array_map(
            static fn (float $probability, int $level): float => $probability * (float) $level,
            $probabilities,
            array_keys($probabilities),
        ));
        if (abs($score - $expected) > $this->scoreValueTolerance) {
            throw new DecisionResponseException(
                "{$context} does not match its probability-weighted value.",
            );
        }

        return new ScoreAnswer(
            questionId: $question->id(),
            value: $score,
            confidence: $this->probability($answer->confidence ?? null, "{$context} confidence"),
            probabilities: ScoreProbabilities::of(...$probabilities),
            legend: ScoreLegend::fromArray($legend),
            signals: $signals,
        );
    }

    private function requiredObject(stdClass $object, string $field, string $context): stdClass
    {
        $value = $object->{$field} ?? null;
        if (! $value instanceof stdClass) {
            throw new DecisionResponseException("{$context} must be an object.");
        }

        return $value;
    }

    private function requiredString(stdClass $object, string $field, string $context): string
    {
        $value = $object->{$field} ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new DecisionResponseException("{$context} must be a non-empty string.");
        }

        return $value;
    }

    private function probability(mixed $value, string $context): float
    {
        $number = $this->finiteNumber($value, $context);
        if ($number < 0.0 || $number > 1.0) {
            throw new DecisionResponseException("{$context} must be between 0 and 1.");
        }

        return $number;
    }

    private function finiteNumber(mixed $value, string $context): float
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new DecisionResponseException("{$context} must be a finite number.");
        }

        return (float) $value;
    }

    /** @param list<float|int> $probabilities */
    private function assertProbabilitySum(array $probabilities, string $context): void
    {
        if (abs(array_sum($probabilities) - 1.0) > $this->probabilitySumTolerance) {
            throw new DecisionResponseException("{$context} probabilities must sum to 1.");
        }
    }

    /** @param list<string> $expected @param list<string> $actual */
    private function assertSameKeys(array $expected, array $actual, string $context): void
    {
        sort($expected, SORT_STRING);
        sort($actual, SORT_STRING);
        if ($expected !== $actual) {
            throw new DecisionResponseException("{$context} must exactly match the request.");
        }
    }

    /** @return list<string> */
    private function objectKeys(stdClass $object): array
    {
        return array_map('strval', array_keys(get_object_vars($object)));
    }

    /** @param list<string> $fields */
    private function assertExactFields(stdClass $object, array $fields, string $context): void
    {
        $this->assertSameKeys($fields, $this->objectKeys($object), "{$context} fields");
    }

    private function sameJson(mixed $left, mixed $right): bool
    {
        return match (true) {
            $left instanceof stdClass && $right instanceof stdClass => $this->sameJsonObject($left, $right),
            is_array($left) && is_array($right) => $this->sameJsonArray($left, $right),
            default => $left === $right,
        };
    }

    private function sameJsonObject(stdClass $left, stdClass $right): bool
    {
        $leftKeys = $this->objectKeys($left);
        $rightKeys = $this->objectKeys($right);
        sort($leftKeys, SORT_STRING);
        sort($rightKeys, SORT_STRING);
        if ($leftKeys !== $rightKeys) {
            return false;
        }
        $rightFields = get_object_vars($right);
        foreach (get_object_vars($left) as $key => $value) {
            if (! $this->sameJson($value, $rightFields[$key])) {
                return false;
            }
        }

        return true;
    }

    private function sameJsonArray(array $left, array $right): bool
    {
        if (array_is_list($left) !== array_is_list($right) || count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $key => $value) {
            if (! array_key_exists($key, $right) || ! $this->sameJson($value, $right[$key])) {
                return false;
            }
        }

        return true;
    }
}
