<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\Laya;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Answers\AnswerSignals;
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

final readonly class LayaResponseAdapter implements DecisionResponseAdapter
{
    public const float PROBABILITY_SUM_TOLERANCE = 0.02;
    public const float SCORE_VALUE_TOLERANCE = 0.02;
    public const float NOUL_CONFIDENCE_TOLERANCE = 0.0002;

    private SystemOneAnswerDecoder $decoder;

    public function __construct(?SystemOneAnswerDecoder $decoder = null) {
        $this->decoder = $decoder ?? new SystemOneAnswerDecoder(
            provider: 'Laya',
            probabilitySumTolerance: self::PROBABILITY_SUM_TOLERANCE,
            scoreValueTolerance: self::SCORE_VALUE_TOLERANCE,
        );
    }

    #[Override]
    public function fromHttpResponse(HttpResponse $response, DecisionRequest $request): DecisionResponse {
        $this->assertJsonContentType($response);
        $body = $this->decodeBody($response);
        $this->assertExactFields($body, ['model', 'answers', 'usage'], 'Laya response');
        $wireAnswers = $this->requiredObject($body, 'answers', 'Laya response answers');
        [$normalizedAnswers, $signals] = $this->normalizeAnswers($wireAnswers, $request);

        return $this->decoder->decode(
            wireAnswers: $normalizedAnswers,
            request: $request,
            model: $this->requiredString($body, 'model', 'Laya response model'),
            usage: $this->usage($body),
            responseData: $response,
            providerRequestId: new DecisionProviderRequestId($this->header($response, 'x-request-id') ?? ''),
            signals: $signals,
        );
    }

    /** @return array{stdClass, array<string, AnswerSignals>} */
    private function normalizeAnswers(stdClass $answers, DecisionRequest $request): array {
        $questionIds = array_map(
            static fn (Noul|Choice|Score $question): string => $question->id(),
            $request->questions()->all(),
        );
        $this->assertSameKeys($questionIds, $this->objectKeys($answers), 'Laya answer IDs');
        $normalized = new stdClass();
        $signals = [];
        foreach ($request->questions()->all() as $question) {
            $answer = $answers->{$question->id()} ?? null;
            if (!$answer instanceof stdClass) {
                throw new DecisionResponseException('Laya answer must be an object.');
            }
            [$normalizedAnswer, $signal] = $this->normalizeAnswer($question, $answer);
            $normalized->{$question->id()} = $normalizedAnswer;
            $signals[$question->id()] = $signal;
        }

        return [$normalized, $signals];
    }

    /** @return array{stdClass, AnswerSignals} */
    private function normalizeAnswer(Noul|Choice|Score $question, stdClass $answer): array {
        $type = $this->requiredString($answer, 'type', 'Laya answer type');
        $fields = match (true) {
            $question instanceof Choice && $type === 'choice' => [
                'type', 'choice', 'confidence', 'probabilities', 'action',
            ],
            $question instanceof Score && $type === 'score' => [
                'type', 'score', 'confidence', 'probabilities', 'legend', 'action',
            ],
            $question instanceof Noul && $type === 'noul' => [
                'type', 'noul', 'confidence', 'action',
            ],
            default => throw new DecisionResponseException(
                'Laya answer type does not match its question.',
            ),
        };
        $this->assertExactFields($answer, $fields, 'Laya answer');
        $action = $this->requiredObject($answer, 'action', 'Laya answer action');
        $this->assertExactFields($action, ['act_probability'], 'Laya answer action');
        $signal = new AnswerSignals(
            modelActionProbability: $this->probability(
                $action->act_probability ?? null,
                'Laya action probability',
            ),
        );

        return match (true) {
            $question instanceof Noul => [$this->normalizedNoul($answer), $signal],
            default => [$this->withoutExtension($answer, 'action'), $signal],
        };
    }

    private function normalizedNoul(stdClass $answer): stdClass {
        $probability = $this->probability($answer->noul ?? null, 'Laya Noul probability');
        $confidence = $this->probability($answer->confidence ?? null, 'Laya Noul confidence');
        if (abs($confidence - max($probability, 1.0 - $probability)) > self::NOUL_CONFIDENCE_TOLERANCE) {
            throw new DecisionResponseException('Laya Noul confidence is inconsistent.');
        }

        $normalized = new stdClass();
        $normalized->type = 'noul';
        $normalized->noul = $probability;

        return $normalized;
    }

    private function withoutExtension(stdClass $answer, string $extension): stdClass {
        $normalized = new stdClass();
        foreach (get_object_vars($answer) as $field => $value) {
            if ($field !== $extension) {
                $normalized->{$field} = $value;
            }
        }

        return $normalized;
    }

    private function decodeBody(HttpResponse $response): stdClass {
        try {
            $body = json_decode($response->body(), associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DecisionResponseException('Laya returned malformed JSON.', $response->statusCode());
        }
        if (!$body instanceof stdClass) {
            throw new DecisionResponseException(
                'Laya response body must be a JSON object.',
                $response->statusCode(),
            );
        }

        return $body;
    }

    private function usage(stdClass $body): DecisionUsage {
        $usage = $this->requiredObject($body, 'usage', 'Laya response usage');
        $this->assertExactFields($usage, ['input_tokens', 'output_tokens'], 'Laya response usage');

        return new DecisionUsage(
            inputTokens: $this->nonNegativeInt($usage, 'input_tokens'),
            outputTokens: $this->nonNegativeInt($usage, 'output_tokens'),
        );
    }

    private function nonNegativeInt(stdClass $object, string $field): int {
        $value = $object->{$field} ?? null;
        if (!is_int($value) || $value < 0) {
            throw new DecisionResponseException("Laya usage field '{$field}' must be a non-negative integer.");
        }

        return $value;
    }

    private function assertJsonContentType(HttpResponse $response): void {
        $contentType = $this->header($response, 'content-type');
        if ($contentType === null || strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new DecisionResponseException(
                'Laya response content type must be application/json.',
                $response->statusCode(),
            );
        }
    }

    private function requiredObject(stdClass $object, string $field, string $context): stdClass {
        $value = $object->{$field} ?? null;
        if (!$value instanceof stdClass) {
            throw new DecisionResponseException("{$context} must be an object.");
        }

        return $value;
    }

    private function requiredString(stdClass $object, string $field, string $context): string {
        $value = $object->{$field} ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new DecisionResponseException("{$context} must be a non-empty string.");
        }

        return $value;
    }

    private function probability(mixed $value, string $context): float {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new DecisionResponseException("{$context} must be a finite number.");
        }
        $probability = (float) $value;
        if ($probability < 0.0 || $probability > 1.0) {
            throw new DecisionResponseException("{$context} must be between 0 and 1.");
        }

        return $probability;
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

    /** @param list<string> $expected @param list<string> $actual */
    private function assertSameKeys(array $expected, array $actual, string $context): void {
        sort($expected, SORT_STRING);
        sort($actual, SORT_STRING);
        if ($expected !== $actual) {
            throw new DecisionResponseException("{$context} must exactly match the protocol.");
        }
    }

    /** @return list<string> */
    private function objectKeys(stdClass $object): array {
        return array_map('strval', array_keys(get_object_vars($object)));
    }

    /** @param list<string> $fields */
    private function assertExactFields(stdClass $object, array $fields, string $context): void {
        $this->assertSameKeys($fields, $this->objectKeys($object), "{$context} fields");
    }
}
