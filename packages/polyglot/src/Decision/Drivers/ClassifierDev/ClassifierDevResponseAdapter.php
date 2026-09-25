<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\ClassifierDev;

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
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use InvalidArgumentException;
use JsonException;
use Override;
use stdClass;

final readonly class ClassifierDevResponseAdapter implements DecisionResponseAdapter
{
    private const float DISTRIBUTION_TOLERANCE = 0.02;
    private const float MAXIMUM_TOLERANCE = 0.000000001;

    #[Override]
    public function fromHttpResponse(HttpResponse $response, DecisionRequest $request): DecisionResponse {
        $this->assertJsonContentType($response);
        $body = $this->decodeBody($response);
        if ($this->requiredString($body, 'tier', 'response tier') !== 'fast') {
            throw new DecisionResponseException('Classifier.dev returned an unexpected response tier.');
        }
        $model = $this->requiredString($body, 'model', 'response model');
        $results = $body->results ?? null;
        if (!is_array($results) || count($results) !== 1 || !$results[0] instanceof stdClass) {
            throw new DecisionResponseException('Classifier.dev must return exactly one result row.');
        }
        $dimensions = $this->requiredObject($results[0], 'dimensions', 'result dimensions');
        $map = ClassifierDevDimensionMap::fromRequest($request);
        $this->assertDimensionNames($dimensions, $map);

        $answers = [];
        foreach ($map->wireNames() as $wireName) {
            $result = $this->requiredObject($dimensions, $wireName, 'dimension result');
            $answers[] = $this->answer($result, $wireName, $map);
        }

        return new DecisionResponse(
            answers: Answers::of(...$answers),
            model: $model,
            usage: new DecisionUsage(),
            responseData: $response,
            providerRequestId: new DecisionProviderRequestId($this->header($response, 'idempotency-key') ?? ''),
        );
    }

    private function answer(
        stdClass $result,
        string $wireName,
        ClassifierDevDimensionMap $map,
    ): NoulAnswer|ChoiceAnswer|ScoreAnswer {
        $label = $this->requiredString($result, 'label', 'dimension label');
        $confidence = $this->requiredProbability($result, 'confidence', 'dimension confidence');
        $this->requiredString($result, 'model', 'dimension model');
        $this->requiredNonNegativeInt($result, 'ms', 'dimension latency');
        $probabilities = $this->probabilities($result, $wireName, $map);
        $this->assertSelectedIsMaximum($label, $probabilities);
        $question = $map->question($wireName);

        try {
            return match (true) {
                $question instanceof Choice => $this->choiceAnswer(
                    $question,
                    $label,
                    $confidence,
                    $probabilities,
                    $wireName,
                    $map,
                ),
                $question instanceof Noul => $this->noulAnswer($question, $probabilities, $wireName, $map),
                $question instanceof Score => $this->scoreAnswer(
                    $question,
                    $confidence,
                    $probabilities,
                    $wireName,
                    $map,
                ),
            };
        } catch (InvalidArgumentException) {
            throw new DecisionResponseException('Classifier.dev returned an invalid typed answer.');
        }
    }

    /**
     * @param array<string, float> $probabilities
     */
    private function choiceAnswer(
        Choice $question,
        string $wireLabel,
        float $confidence,
        array $probabilities,
        string $wireName,
        ClassifierDevDimensionMap $map,
    ): ChoiceAnswer {
        $entries = [];
        foreach ($map->labels($wireName) as $label) {
            $value = $map->value($wireName, $label);
            if (!is_string($value)) {
                throw new DecisionResponseException('Classifier.dev Choice mapping is invalid.');
            }
            $entries[] = [$value, $probabilities[self::key($label)]];
        }
        $value = $map->value($wireName, $wireLabel);
        if (!is_string($value) || !$question->options()->has($value)) {
            throw new DecisionResponseException('Classifier.dev returned an invalid Choice label.');
        }

        return new ChoiceAnswer(
            questionId: $question->id(),
            value: $value,
            confidence: $confidence,
            probabilities: ChoiceProbabilities::of(...$entries),
        );
    }

    /** @param array<string, float> $probabilities */
    private function noulAnswer(
        Noul $question,
        array $probabilities,
        string $wireName,
        ClassifierDevDimensionMap $map,
    ): NoulAnswer {
        foreach ($map->labels($wireName) as $label) {
            if ($map->value($wireName, $label) === true) {
                return new NoulAnswer($question->id(), $probabilities[self::key($label)]);
            }
        }

        throw new DecisionResponseException('Classifier.dev Noul mapping is invalid.');
    }

    /** @param array<string, float> $probabilities */
    private function scoreAnswer(
        Score $question,
        float $confidence,
        array $probabilities,
        string $wireName,
        ClassifierDevDimensionMap $map,
    ): ScoreAnswer {
        $ordered = array_fill(0, $question->levels()->count(), null);
        foreach ($map->labels($wireName) as $label) {
            $level = $map->value($wireName, $label);
            if (!is_int($level) || !array_key_exists($level, $ordered)) {
                throw new DecisionResponseException('Classifier.dev Score mapping is invalid.');
            }
            $ordered[$level] = $probabilities[self::key($label)];
        }
        if (in_array(null, $ordered, true)) {
            throw new DecisionResponseException('Classifier.dev Score distribution is incomplete.');
        }
        /** @var list<float> $ordered */
        $scoreProbabilities = ScoreProbabilities::of(...$ordered);

        return new ScoreAnswer(
            questionId: $question->id(),
            value: $scoreProbabilities->expectedValue(),
            confidence: $confidence,
            probabilities: $scoreProbabilities,
            legend: ScoreLegend::of(...$question->levels()->all()),
        );
    }

    /** @return array<string, float> */
    private function probabilities(
        stdClass $result,
        string $wireName,
        ClassifierDevDimensionMap $map,
    ): array {
        $scores = $result->scores ?? null;
        if (!$scores instanceof stdClass) {
            throw new DecisionResponseException('Classifier.dev dimension scores must be a complete object.');
        }
        $rawScores = get_object_vars($scores);
        $expected = $map->labels($wireName);
        $actual = array_map(static fn (int|string $label): string => (string) $label, array_keys($rawScores));
        if (array_diff($expected, $actual) !== [] || array_diff($actual, $expected) !== []) {
            throw new DecisionResponseException('Classifier.dev dimension scores do not match requested labels.');
        }

        $probabilities = [];
        foreach ($expected as $label) {
            $value = $rawScores[$label] ?? null;
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                throw new DecisionResponseException('Classifier.dev dimension scores must be finite numbers.');
            }
            $probability = (float) $value;
            if ($probability < 0.0 || $probability > 1.0) {
                throw new DecisionResponseException('Classifier.dev dimension scores must be probabilities.');
            }
            $probabilities[self::key($label)] = $probability;
        }
        if (abs(array_sum($probabilities) - 1.0) > self::DISTRIBUTION_TOLERANCE) {
            throw new DecisionResponseException('Classifier.dev dimension scores must sum to 1.');
        }

        return $probabilities;
    }

    /** @param array<string, float> $probabilities */
    private function assertSelectedIsMaximum(string $wireLabel, array $probabilities): void {
        $key = self::key($wireLabel);
        if (!array_key_exists($key, $probabilities)) {
            throw new DecisionResponseException('Classifier.dev selected an unknown dimension label.');
        }
        if ($probabilities[$key] < max($probabilities) - self::MAXIMUM_TOLERANCE) {
            throw new DecisionResponseException(
                'Classifier.dev selected label must have the highest score.',
            );
        }
    }

    private function assertDimensionNames(stdClass $dimensions, ClassifierDevDimensionMap $map): void {
        $expected = $map->wireNames();
        $actual = array_map(static fn (int|string $name): string => (string) $name, array_keys(get_object_vars($dimensions)));
        if (array_diff($expected, $actual) !== [] || array_diff($actual, $expected) !== []) {
            throw new DecisionResponseException(
                'Classifier.dev result dimensions do not match requested questions.',
            );
        }
    }

    private function decodeBody(HttpResponse $response): stdClass {
        try {
            $body = json_decode($response->body(), associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DecisionResponseException(
                'Classifier.dev returned malformed JSON.',
                $response->statusCode(),
            );
        }
        if (!$body instanceof stdClass) {
            throw new DecisionResponseException(
                'Classifier.dev response body must be a JSON object.',
                $response->statusCode(),
            );
        }

        return $body;
    }

    private function assertJsonContentType(HttpResponse $response): void {
        $contentType = $this->header($response, 'content-type');
        if ($contentType === null || strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new DecisionResponseException(
                'Classifier.dev response content type must be application/json.',
                $response->statusCode(),
            );
        }
    }

    private function header(HttpResponse $response, string $name): ?string {
        foreach ($response->headers() as $header => $value) {
            if (strtolower((string) $header) !== $name) {
                continue;
            }
            $first = match (true) {
                is_array($value) => $value[0] ?? null,
                default => $value,
            };

            return match (true) {
                is_string($first) && trim($first) !== '' => $first,
                default => null,
            };
        }

        return null;
    }

    private function requiredObject(stdClass $object, string $field, string $context): stdClass {
        $value = $object->{$field} ?? null;
        if (!$value instanceof stdClass) {
            throw new DecisionResponseException("Classifier.dev {$context} must be an object.");
        }

        return $value;
    }

    private function requiredString(stdClass $object, string $field, string $context): string {
        $value = $object->{$field} ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new DecisionResponseException("Classifier.dev {$context} must be a non-empty string.");
        }

        return $value;
    }

    private function requiredProbability(stdClass $object, string $field, string $context): float {
        $value = $object->{$field} ?? null;
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new DecisionResponseException("Classifier.dev {$context} must be a finite probability.");
        }
        $probability = (float) $value;
        if ($probability < 0.0 || $probability > 1.0) {
            throw new DecisionResponseException("Classifier.dev {$context} must be between 0 and 1.");
        }

        return $probability;
    }

    private function requiredNonNegativeInt(stdClass $object, string $field, string $context): int {
        $value = $object->{$field} ?? null;
        if (!is_int($value) || $value < 0) {
            throw new DecisionResponseException(
                "Classifier.dev {$context} must be a non-negative integer.",
            );
        }

        return $value;
    }

    private static function key(string $value): string {
        return '#' . $value;
    }
}
