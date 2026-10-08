<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Anthropic;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\BatchInference\Data\BatchItemFailure;
use Cognesy\Polyglot\BatchInference\Data\BatchItemProvenance;
use Cognesy\Polyglot\BatchInference\Data\BatchItemResult;
use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Utils\Result\Result;
use RuntimeException;

final readonly class AnthropicBatchRecordDecoder
{
    private AnthropicResponseAdapter $responses;

    public function __construct()
    {
        $this->responses = new AnthropicResponseAdapter(new AnthropicUsageFormat());
    }

    /** @param array<string, mixed> $record */
    public function decode(array $record, string $artifactId): BatchItemResult
    {
        $key = $record['custom_id'] ?? null;
        $result = $record['result'] ?? null;
        if (!is_string($key) || $key === '' || !is_array($result)) {
            throw new RuntimeException('Anthropic batch result has no custom_id or result.');
        }
        $provenance = new BatchItemProvenance($record, $artifactId, syntheticDecoderEnvelope: true);
        $type = $result['type'] ?? null;
        if ($type === 'succeeded') {
            if (!is_array($result['message'] ?? null)) {
                throw new RuntimeException('Anthropic batch success has no message.');
            }
            $response = $this->responses->fromResponse(HttpResponse::sync(
                200,
                [],
                json_encode($result['message'], JSON_THROW_ON_ERROR),
            ));
            if ($response === null) {
                throw new RuntimeException('Anthropic batch message could not be decoded.');
            }
            return new BatchItemResult($key, Result::success($response), $provenance);
        }

        $kind = match ($type) {
            'canceled' => BatchItemFailureKind::Cancelled,
            'expired' => BatchItemFailureKind::Expired,
            'errored' => BatchItemFailureKind::ProviderError,
            default => throw new RuntimeException('Unknown Anthropic batch result type.'),
        };
        $nativeError = is_array($result['error'] ?? null) ? $result['error'] : [];
        $error = is_array($nativeError['error'] ?? null) ? $nativeError['error'] : $nativeError;
        $code = is_string($error['type'] ?? null) ? $error['type'] : $type;
        $message = is_string($error['message'] ?? null) ? $error['message'] : '';

        return new BatchItemResult($key, Result::failure(new BatchItemFailure($kind, $code, $message)), $provenance);
    }
}
