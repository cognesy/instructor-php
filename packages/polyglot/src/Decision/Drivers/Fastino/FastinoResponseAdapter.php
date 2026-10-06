<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\Fastino;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Contracts\DecisionResponseAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionProviderRequestId;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Decision\Drivers\SystemOne\SystemOneAnswerDecoder;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use JsonException;
use Override;
use stdClass;

/**
 * Normalizes GLiDE answers to the shared System One shape: Noul confidence and
 * the Score argmax index are validated, then dropped; `expected_level` becomes
 * the weighted Score value.
 */
final readonly class FastinoResponseAdapter implements DecisionResponseAdapter
{
    public const float PROBABILITY_SUM_TOLERANCE = 0.001;

    public const float SCORE_VALUE_TOLERANCE = 0.001;

    public const float CHOICE_WINNER_TOLERANCE = 0.000000001;

    public const float NOUL_CONFIDENCE_TOLERANCE = 0.0002;

    private SystemOneAnswerDecoder $decoder;

    public function __construct(?SystemOneAnswerDecoder $decoder = null)
    {
        $this->decoder = $decoder ?? new SystemOneAnswerDecoder(
            provider: 'Fastino',
            probabilitySumTolerance: self::PROBABILITY_SUM_TOLERANCE,
            scoreValueTolerance: self::SCORE_VALUE_TOLERANCE,
            choiceWinnerTolerance: self::CHOICE_WINNER_TOLERANCE,
        );
    }

    #[Override]
    public function fromHttpResponse(HttpResponse $response, DecisionRequest $request): DecisionResponse
    {
        $this->assertJsonContentType($response);
        $body = $this->decodeBody($response);

        return $this->decoder->decode(
            wireAnswers: $this->normalizeAnswers(
                $this->requiredObject($body, 'answers', 'Fastino response answers'),
                $request,
            ),
            request: $request,
            model: $this->requiredString($body, 'model', 'Fastino response model'),
            usage: $this->usage($body),
            responseData: $response,
            providerRequestId: new DecisionProviderRequestId($this->header($response, 'x-request-id') ?? ''),
        );
    }

    private function normalizeAnswers(stdClass $answers, DecisionRequest $request): stdClass
    {
        $normalized = new stdClass;
        foreach (get_object_vars($answers) as $id => $answer) {
            $normalized->{$id} = $answer;
        }
        foreach ($request->questions()->all() as $question) {
            $answer = $answers->{$question->id()} ?? null;
            if (! $answer instanceof stdClass) {
                continue;
            }
            $normalized->{$question->id()} = $this->normalizeAnswer($question, $answer);
        }

        return $normalized;
    }

    private function normalizeAnswer(Noul|Choice|Score $question, stdClass $answer): stdClass
    {
        $context = "Fastino answer '{$question->id()}'";
        $type = $answer->type ?? null;

        return match (true) {
            $question instanceof Noul && $type === 'noul' => $this->normalizedNoul($answer, $context),
            $question instanceof Score && $type === 'score' => $this->normalizedScore($answer, $context),
            default => $answer,
        };
    }

    private function normalizedNoul(stdClass $answer, string $context): stdClass
    {
        $this->assertExactFields($answer, ['type', 'noul', 'confidence'], $context);
        $probability = $this->probability($answer->noul ?? null, "{$context} probability");
        $confidence = $this->probability($answer->confidence ?? null, "{$context} confidence");
        if (abs($confidence - abs(2.0 * $probability - 1.0)) > self::NOUL_CONFIDENCE_TOLERANCE) {
            throw new DecisionResponseException("{$context} Noul confidence is inconsistent.");
        }

        $normalized = new stdClass;
        $normalized->type = 'noul';
        $normalized->noul = $answer->noul;

        return $normalized;
    }

    private function normalizedScore(stdClass $answer, string $context): stdClass
    {
        $this->assertExactFields(
            $answer,
            ['type', 'score', 'expected_level', 'confidence', 'probabilities', 'legend'],
            $context,
        );
        $probabilities = $this->requiredObject($answer, 'probabilities', "{$context} probabilities");
        $level = $answer->score ?? null;
        $levelProbability = match (is_int($level)) {
            true => $probabilities->{(string) $level} ?? null,
            false => null,
        };
        $highest = array_reduce(
            get_object_vars($probabilities),
            static fn (float $carry, mixed $value): float => is_int($value) || is_float($value) ? max($carry, (float) $value) : $carry,
            0.0,
        );
        if ((! is_int($levelProbability) && ! is_float($levelProbability))
            || $levelProbability < $highest - self::CHOICE_WINNER_TOLERANCE
        ) {
            throw new DecisionResponseException("{$context} score must be the most probable level index.");
        }

        $normalized = new stdClass;
        $normalized->type = 'score';
        $normalized->score = $answer->expected_level;
        $normalized->confidence = $answer->confidence;
        $normalized->probabilities = $probabilities;
        $normalized->legend = $answer->legend;

        return $normalized;
    }

    private function decodeBody(HttpResponse $response): stdClass
    {
        try {
            $body = json_decode($response->body(), associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DecisionResponseException('Fastino returned malformed JSON.', $response->statusCode());
        }
        if (! $body instanceof stdClass) {
            throw new DecisionResponseException(
                'Fastino response body must be a JSON object.',
                $response->statusCode(),
            );
        }

        return $body;
    }

    private function usage(stdClass $body): DecisionUsage
    {
        $usage = $this->requiredObject($body, 'usage', 'Fastino response usage');

        return new DecisionUsage(
            inputTokens: $this->nonNegativeInt($usage, 'input_tokens'),
            outputTokens: $this->nonNegativeInt($usage, 'output_tokens'),
        );
    }

    private function nonNegativeInt(stdClass $object, string $field): int
    {
        $value = $object->{$field} ?? null;
        if (! is_int($value) || $value < 0) {
            throw new DecisionResponseException("Fastino usage field '{$field}' must be a non-negative integer.");
        }

        return $value;
    }

    private function probability(mixed $value, string $context): float
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new DecisionResponseException("{$context} must be a finite number.");
        }
        $probability = (float) $value;
        if ($probability < 0.0 || $probability > 1.0) {
            throw new DecisionResponseException("{$context} must be between 0 and 1.");
        }

        return $probability;
    }

    private function assertJsonContentType(HttpResponse $response): void
    {
        $contentType = $this->header($response, 'content-type');
        if ($contentType === null || strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new DecisionResponseException(
                'Fastino response content type must be application/json.',
                $response->statusCode(),
            );
        }
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

    /** @param list<string> $fields */
    private function assertExactFields(stdClass $object, array $fields, string $context): void
    {
        $actual = array_map('strval', array_keys(get_object_vars($object)));
        sort($fields, SORT_STRING);
        sort($actual, SORT_STRING);
        if ($fields !== $actual) {
            throw new DecisionResponseException("{$context} fields must exactly match the protocol.");
        }
    }
}
