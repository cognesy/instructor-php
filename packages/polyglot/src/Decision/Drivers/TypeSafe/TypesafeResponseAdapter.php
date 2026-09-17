<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\TypeSafe;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Answers\ChoiceAnswer;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Answers\ScoreAnswer;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\ChoiceProbabilities;
use Cognesy\Polyglot\Decision\Collections\ScoreLegend;
use Cognesy\Polyglot\Decision\Collections\ScoreProbabilities;
use Cognesy\Polyglot\Decision\Contracts\DecisionResponseAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionProviderRequestId;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use JsonException;
use Override;
use stdClass;

final readonly class TypesafeResponseAdapter implements DecisionResponseAdapter
{
    public const float PROBABILITY_SUM_TOLERANCE = 0.001;

    public const float SCORE_VALUE_TOLERANCE = 0.001;

    public const float CHOICE_WINNER_TOLERANCE = 0.000000001;

    #[Override]
    public function fromHttpResponse(HttpResponse $response, DecisionRequest $request): DecisionResponse
    {
        $this->assertJsonContentType($response);
        $body = $this->decodeBody($response);
        $model = $this->requiredString($body, 'model', 'TypeSafe response model');
        $wireAnswers = $this->requiredObject($body, 'answers', 'TypeSafe response answers');
        $this->assertSameKeys(
            expected: array_map(static fn (Noul|Choice|Score $question): string => $question->id(), $request->questions()->all()),
            actual: $this->objectKeys($wireAnswers),
            context: 'TypeSafe answer IDs',
        );

        $answers = [];
        foreach ($request->questions()->all() as $question) {
            $wireAnswer = $wireAnswers->{$question->id()} ?? null;
            if (! $wireAnswer instanceof stdClass) {
                throw new DecisionResponseException("TypeSafe answer '{$question->id()}' must be an object.");
            }
            $answers[] = $this->answer($question, $wireAnswer);
        }

        return new DecisionResponse(
            answers: Answers::of(...$answers),
            model: $model,
            usage: $this->usage($body),
            responseData: $response,
            providerRequestId: new DecisionProviderRequestId($this->providerRequestId($response)),
        );
    }

    private function decodeBody(HttpResponse $response): stdClass
    {
        try {
            $body = json_decode($response->body(), associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DecisionResponseException('TypeSafe returned malformed JSON.', $response->statusCode());
        }
        if (! $body instanceof stdClass) {
            throw new DecisionResponseException('TypeSafe response body must be a JSON object.', $response->statusCode());
        }

        return $body;
    }

    private function answer(Noul|Choice|Score $question, stdClass $answer): NoulAnswer|ChoiceAnswer|ScoreAnswer
    {
        $type = $this->requiredString($answer, 'type', "TypeSafe answer '{$question->id()}' type");

        return match (true) {
            $question instanceof Noul && $type === 'noul' => $this->noulAnswer($question, $answer),
            $question instanceof Choice && $type === 'choice' => $this->choiceAnswer($question, $answer),
            $question instanceof Score && $type === 'score' => $this->scoreAnswer($question, $answer),
            default => throw new DecisionResponseException(
                "TypeSafe answer '{$question->id()}' type does not match its question.",
            ),
        };
    }

    private function noulAnswer(Noul $question, stdClass $answer): NoulAnswer
    {
        $this->assertExactFields($answer, ['type', 'noul'], "TypeSafe Noul answer '{$question->id()}'");

        return new NoulAnswer(
            questionId: $question->id(),
            probability: $this->probability($answer->noul ?? null, "TypeSafe Noul answer '{$question->id()}'"),
        );
    }

    private function choiceAnswer(Choice $question, stdClass $answer): ChoiceAnswer
    {
        $this->assertExactFields(
            $answer,
            ['type', 'choice', 'confidence', 'probabilities'],
            "TypeSafe Choice answer '{$question->id()}'",
        );
        $choice = $this->requiredString($answer, 'choice', "TypeSafe Choice answer '{$question->id()}' choice");
        if (! $question->options()->has($choice)) {
            throw new DecisionResponseException("TypeSafe Choice answer '{$question->id()}' selected an unknown option.");
        }
        $wireProbabilities = $this->requiredObject(
            $answer,
            'probabilities',
            "TypeSafe Choice answer '{$question->id()}' probabilities",
        );
        $optionIds = array_map(static fn ($option): string => $option->id(), $question->options()->all());
        $this->assertSameKeys(
            $optionIds,
            $this->objectKeys($wireProbabilities),
            "TypeSafe Choice answer '{$question->id()}' probability keys",
        );
        $entries = [];
        foreach ($optionIds as $optionId) {
            $entries[] = [
                $optionId,
                $this->probability(
                    $wireProbabilities->{$optionId} ?? null,
                    "TypeSafe Choice answer '{$question->id()}' probability",
                ),
            ];
        }
        $this->assertProbabilitySum(array_column($entries, 1), "TypeSafe Choice answer '{$question->id()}'");
        if ($entries === []) {
            throw new DecisionResponseException("TypeSafe Choice answer '{$question->id()}' has no probabilities.");
        }
        $probabilitiesById = array_column($entries, 1, 0);
        if ($probabilitiesById[$choice] < max($probabilitiesById) - self::CHOICE_WINNER_TOLERANCE) {
            throw new DecisionResponseException(
                "TypeSafe Choice answer '{$question->id()}' choice must have the highest probability.",
            );
        }

        return new ChoiceAnswer(
            questionId: $question->id(),
            value: $choice,
            confidence: $this->probability(
                $answer->confidence ?? null,
                "TypeSafe Choice answer '{$question->id()}' confidence",
            ),
            probabilities: ChoiceProbabilities::of(...$entries),
        );
    }

    private function scoreAnswer(Score $question, stdClass $answer): ScoreAnswer
    {
        $this->assertExactFields(
            $answer,
            ['type', 'score', 'confidence', 'probabilities', 'legend'],
            "TypeSafe Score answer '{$question->id()}'",
        );
        $wireProbabilities = $this->requiredObject(
            $answer,
            'probabilities',
            "TypeSafe Score answer '{$question->id()}' probabilities",
        );
        $wireLegend = $this->requiredObject(
            $answer,
            'legend',
            "TypeSafe Score answer '{$question->id()}' legend",
        );
        $levelKeys = array_map('strval', range(0, $question->levels()->count() - 1));
        $this->assertSameKeys(
            $levelKeys,
            $this->objectKeys($wireProbabilities),
            "TypeSafe Score answer '{$question->id()}' probability keys",
        );
        $this->assertSameKeys(
            $levelKeys,
            $this->objectKeys($wireLegend),
            "TypeSafe Score answer '{$question->id()}' legend keys",
        );

        $probabilities = [];
        $legend = [];
        foreach ($levelKeys as $level => $key) {
            $probabilities[] = $this->probability(
                $wireProbabilities->{$key} ?? null,
                "TypeSafe Score answer '{$question->id()}' probability",
            );
            $wireDescription = $wireLegend->{$key} ?? null;
            if (! is_string($wireDescription) && ! is_array($wireDescription) && ! $wireDescription instanceof stdClass) {
                throw new DecisionResponseException(
                    "TypeSafe Score answer '{$question->id()}' legend entry must be text, an object, or a list.",
                );
            }
            $description = JsonContent::from($wireDescription);
            if (! $this->sameJson($description->value(), $question->levels()->at($level)->value())) {
                throw new DecisionResponseException(
                    "TypeSafe Score answer '{$question->id()}' legend does not match its question.",
                );
            }
            $legend[] = $wireDescription;
        }
        $this->assertProbabilitySum($probabilities, "TypeSafe Score answer '{$question->id()}'");
        $score = $this->finiteNumber($answer->score ?? null, "TypeSafe Score answer '{$question->id()}' score");
        $expected = array_sum(array_map(
            static fn (float $probability, int $level): float => $probability * (float) $level,
            $probabilities,
            array_keys($probabilities),
        ));
        if (abs($score - $expected) > self::SCORE_VALUE_TOLERANCE) {
            throw new DecisionResponseException(
                "TypeSafe Score answer '{$question->id()}' does not match its probability-weighted value.",
            );
        }

        return new ScoreAnswer(
            questionId: $question->id(),
            value: $score,
            confidence: $this->probability(
                $answer->confidence ?? null,
                "TypeSafe Score answer '{$question->id()}' confidence",
            ),
            probabilities: ScoreProbabilities::of(...$probabilities),
            legend: ScoreLegend::fromArray($legend),
        );
    }

    private function usage(stdClass $body): DecisionUsage
    {
        $usage = $this->requiredObject($body, 'usage', 'TypeSafe response usage');

        return new DecisionUsage(
            inputTokens: $this->optionalNonNegativeInt($usage, 'input_tokens'),
            outputTokens: $this->optionalNonNegativeInt($usage, 'output_tokens'),
        );
    }

    private function optionalNonNegativeInt(stdClass $object, string $field): ?int
    {
        if (! property_exists($object, $field) || $object->{$field} === null) {
            return null;
        }
        if (! is_int($object->{$field}) || $object->{$field} < 0) {
            throw new DecisionResponseException("TypeSafe usage field '{$field}' must be a non-negative integer or null.");
        }

        return $object->{$field};
    }

    private function assertJsonContentType(HttpResponse $response): void
    {
        $contentType = $this->header($response, 'content-type');
        if ($contentType === null || strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new DecisionResponseException('TypeSafe response content type must be application/json.', $response->statusCode());
        }
    }

    private function providerRequestId(HttpResponse $response): string
    {
        return $this->header($response, 'x-typesafe-request-id') ?? '';
    }

    private function header(HttpResponse $response, string $name): ?string
    {
        foreach ($response->headers() as $header => $value) {
            if (strtolower((string) $header) !== $name) {
                continue;
            }
            $first = is_array($value) ? ($value[0] ?? null) : $value;

            return is_string($first) && trim($first) !== '' ? $first : null;
        }

        return null;
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
        if (abs(array_sum($probabilities) - 1.0) > self::PROBABILITY_SUM_TOLERANCE) {
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
