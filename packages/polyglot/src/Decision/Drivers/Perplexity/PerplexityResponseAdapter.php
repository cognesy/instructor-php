<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\Perplexity;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Contracts\DecisionResponseAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionProviderRequestId;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Decision\Drivers\SystemOne\SystemOneAnswerDecoder;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use JsonException;
use Override;
use stdClass;

final readonly class PerplexityResponseAdapter implements DecisionResponseAdapter
{
    public const float PROBABILITY_SUM_TOLERANCE = 0.01;

    public const float SCORE_VALUE_TOLERANCE = 0.01;

    private SystemOneAnswerDecoder $decoder;

    public function __construct(?SystemOneAnswerDecoder $decoder = null)
    {
        $this->decoder = $decoder ?? new SystemOneAnswerDecoder(
            provider: 'Perplexity',
            probabilitySumTolerance: self::PROBABILITY_SUM_TOLERANCE,
            scoreValueTolerance: self::SCORE_VALUE_TOLERANCE,
        );
    }

    #[Override]
    public function fromHttpResponse(HttpResponse $response, DecisionRequest $request): DecisionResponse
    {
        $this->assertJsonContentType($response);
        $body = $this->decodeBody($response);

        return $this->decoder->decode(
            wireAnswers: $this->requiredObject($body, 'answers', 'Perplexity response answers'),
            request: $request,
            model: $this->requiredString($body, 'model', 'Perplexity response model'),
            usage: $this->usage($body),
            responseData: $response,
            providerRequestId: new DecisionProviderRequestId($this->header($response, 'x-request-id') ?? ''),
        );
    }

    private function decodeBody(HttpResponse $response): stdClass
    {
        try {
            $body = json_decode($response->body(), associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DecisionResponseException('Perplexity returned malformed JSON.', $response->statusCode());
        }
        if (! $body instanceof stdClass) {
            throw new DecisionResponseException(
                'Perplexity response body must be a JSON object.',
                $response->statusCode(),
            );
        }

        return $body;
    }

    private function usage(stdClass $body): DecisionUsage
    {
        $usage = $this->requiredObject($body, 'usage', 'Perplexity response usage');

        return new DecisionUsage(
            inputTokens: $this->nonNegativeInt($usage, 'input_tokens'),
            outputTokens: $this->nonNegativeInt($usage, 'output_tokens'),
        );
    }

    private function nonNegativeInt(stdClass $object, string $field): int
    {
        $value = $object->{$field} ?? null;
        if (! is_int($value) || $value < 0) {
            throw new DecisionResponseException("Perplexity usage field '{$field}' must be a non-negative integer.");
        }

        return $value;
    }

    private function assertJsonContentType(HttpResponse $response): void
    {
        $contentType = $this->header($response, 'content-type');
        if ($contentType === null || strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new DecisionResponseException(
                'Perplexity response content type must be application/json.',
                $response->statusCode(),
            );
        }
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

    private function header(HttpResponse $response, string $name): ?string
    {
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
}
