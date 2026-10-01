<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\Respan;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\NoulProbabilities;
use Cognesy\Polyglot\Decision\Contracts\DecisionResponseAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionProviderRequestId;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Noul;
use JsonException;
use Override;
use stdClass;

final readonly class RespanResponseAdapter implements DecisionResponseAdapter
{
    #[Override]
    public function fromHttpResponse(HttpResponse $response, DecisionRequest $request): DecisionResponse
    {
        $this->assertJsonContentType($response);
        $body = $this->decodeBody($response);
        $this->assertExactFields($body, ['model', 'results'], ['usage'], 'RESPAN response');
        $model = $this->requiredString($body, 'model', 'RESPAN response model');
        if ($request->model() !== null && $request->model() !== $model) {
            throw new DecisionResponseException('RESPAN response model does not match the request.');
        }
        $results = $body->results ?? null;
        if (! is_array($results) || ! array_is_list($results)) {
            throw new DecisionResponseException('RESPAN response results must be a list.');
        }

        $answers = $this->answers($results, $request);

        return new DecisionResponse(
            answers: Answers::of(...$answers),
            model: $model,
            usage: $this->usage($body),
            responseData: $response,
            providerRequestId: new DecisionProviderRequestId(
                $this->header($response, 'x-respan-log-id') ?? '',
            ),
        );
    }

    /** @param list<mixed> $results @return list<NoulAnswer> */
    private function answers(array $results, DecisionRequest $request): array
    {
        $byId = [];
        foreach ($results as $result) {
            if (! $result instanceof stdClass) {
                throw new DecisionResponseException('Each RESPAN result must be an object.');
            }
            $this->assertExactFields(
                $result,
                ['id', 'p_present', 'p_absent', 'p_not_observable'],
                [],
                'RESPAN result',
            );
            $id = $this->requiredString($result, 'id', 'RESPAN result ID');
            if (array_key_exists('#'.$id, $byId)) {
                throw new DecisionResponseException('RESPAN result IDs must be unique.');
            }
            $byId['#'.$id] = $result;
        }

        $answers = [];
        foreach ($request->questions()->all() as $question) {
            if (! $question instanceof Noul) {
                throw new DecisionResponseException('RESPAN supports only Noul answers.');
            }
            $result = $byId['#'.$question->id()] ?? null;
            if (! $result instanceof stdClass) {
                throw new DecisionResponseException("RESPAN result '{$question->id()}' is missing.");
            }
            unset($byId['#'.$question->id()]);
            try {
                $probabilities = NoulProbabilities::of(
                    positive: $this->probability($result->p_present ?? null, 'RESPAN present probability'),
                    negative: $this->probability($result->p_absent ?? null, 'RESPAN absent probability'),
                    unknown: $this->probability(
                        $result->p_not_observable ?? null,
                        'RESPAN not-observable probability',
                    ),
                );
            } catch (\InvalidArgumentException $exception) {
                throw new DecisionResponseException($exception->getMessage());
            }
            $answers[] = NoulAnswer::fromProbabilities($question->id(), $probabilities);
        }
        if ($byId !== []) {
            throw new DecisionResponseException('RESPAN returned unexpected result IDs.');
        }

        return $answers;
    }

    private function usage(stdClass $body): DecisionUsage
    {
        if (! property_exists($body, 'usage')) {
            return new DecisionUsage;
        }
        $usage = $body->usage;
        if (! $usage instanceof stdClass) {
            throw new DecisionResponseException('RESPAN response usage must be an object.');
        }
        $this->assertExactFields($usage, ['input_tokens'], [], 'RESPAN response usage');
        $inputTokens = $usage->input_tokens ?? null;
        if (! is_int($inputTokens) || $inputTokens < 0) {
            throw new DecisionResponseException('RESPAN input token usage must be a non-negative integer.');
        }

        return new DecisionUsage(inputTokens: $inputTokens, outputTokens: null);
    }

    private function decodeBody(HttpResponse $response): stdClass
    {
        try {
            $body = json_decode($response->body(), associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DecisionResponseException('RESPAN returned malformed JSON.', $response->statusCode());
        }
        if (! $body instanceof stdClass) {
            throw new DecisionResponseException('RESPAN response body must be a JSON object.', $response->statusCode());
        }

        return $body;
    }

    private function assertJsonContentType(HttpResponse $response): void
    {
        $contentType = $this->header($response, 'content-type');
        if ($contentType === null || strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new DecisionResponseException(
                'RESPAN response content type must be application/json.',
                $response->statusCode(),
            );
        }
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
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new DecisionResponseException("{$context} must be a finite number.");
        }
        $probability = (float) $value;
        if ($probability < 0.0 || $probability > 1.0) {
            throw new DecisionResponseException("{$context} must be between 0 and 1.");
        }

        return $probability;
    }

    /** @param list<string> $required @param list<string> $optional */
    private function assertExactFields(
        stdClass $object,
        array $required,
        array $optional,
        string $context,
    ): void {
        $actual = array_keys(get_object_vars($object));
        $allowed = [...$required, ...$optional];
        $unknown = array_diff($actual, $allowed);
        $missing = array_diff($required, $actual);
        if ($unknown !== [] || $missing !== []) {
            throw new DecisionResponseException("{$context} fields are invalid.");
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
}
