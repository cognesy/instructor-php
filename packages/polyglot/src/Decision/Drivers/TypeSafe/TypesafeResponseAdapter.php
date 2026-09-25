<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\TypeSafe;

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

final readonly class TypesafeResponseAdapter implements DecisionResponseAdapter
{
    public const float PROBABILITY_SUM_TOLERANCE = 0.001;

    public const float SCORE_VALUE_TOLERANCE = 0.001;

    public const float CHOICE_WINNER_TOLERANCE = 0.000000001;

    private SystemOneAnswerDecoder $decoder;

    public function __construct(?SystemOneAnswerDecoder $decoder = null)
    {
        $this->decoder = $decoder ?? new SystemOneAnswerDecoder(
            provider: 'TypeSafe',
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
            wireAnswers: $this->requiredObject($body, 'answers', 'TypeSafe response answers'),
            request: $request,
            model: $this->requiredString($body, 'model', 'TypeSafe response model'),
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
            throw new DecisionResponseException(
                'TypeSafe response body must be a JSON object.',
                $response->statusCode(),
            );
        }

        return $body;
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
            throw new DecisionResponseException(
                "TypeSafe usage field '{$field}' must be a non-negative integer or null.",
            );
        }

        return $object->{$field};
    }

    private function assertJsonContentType(HttpResponse $response): void
    {
        $contentType = $this->header($response, 'content-type');
        if ($contentType === null || strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new DecisionResponseException(
                'TypeSafe response content type must be application/json.',
                $response->statusCode(),
            );
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
}
