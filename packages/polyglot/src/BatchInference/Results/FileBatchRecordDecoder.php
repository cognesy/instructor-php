<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Results;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\BatchInference\Data\BatchItemFailure;
use Cognesy\Polyglot\BatchInference\Data\BatchItemProvenance;
use Cognesy\Polyglot\BatchInference\Data\BatchItemResult;
use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Groq\GroqUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Qwen\QwenResponseAdapter;
use Cognesy\Utils\Result\Result;
use RuntimeException;

final readonly class FileBatchRecordDecoder
{
    private CanTranslateInferenceResponse $responses;

    public function __construct(string $codec)
    {
        $this->responses = match ($codec) {
            'openai-chat' => new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            'openai-responses' => new OpenResponsesResponseAdapter(new OpenResponsesUsageFormat()),
            'groq-chat' => new OpenAIResponseAdapter(new GroqUsageFormat()),
            'together-chat' => new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            'qwen-chat' => new QwenResponseAdapter(new OpenAIUsageFormat()),
            default => throw new RuntimeException('No inference response codec for this file batch endpoint.'),
        };
    }

    /** @param array<string, mixed> $record */
    public function decode(array $record, string $artifactId): BatchItemResult
    {
        $key = $record['custom_id'] ?? null;
        $error = $record['error'] ?? null;
        if ((!is_string($key) || $key === '') && !is_array($error)) {
            throw new RuntimeException('File batch record has no custom_id.');
        }
        $key = is_string($key) && $key !== '' ? $key : null;
        $provenance = new BatchItemProvenance(
            nativeRecord: $record,
            artifactId: $artifactId,
            nativeRecordId: is_string($record['id'] ?? null) ? $record['id'] : null,
            syntheticDecoderEnvelope: true,
        );
        if (is_array($error)) {
            $code = (string) ($error['code'] ?? 'provider_error');
            $kind = match ($code) {
                'batch_expired' => BatchItemFailureKind::Expired,
                'batch_cancelled', 'batch_canceled' => BatchItemFailureKind::Cancelled,
                default => BatchItemFailureKind::ProviderError,
            };
            return new BatchItemResult($key, Result::failure(new BatchItemFailure($kind, $code, (string) ($error['message'] ?? ''))), $provenance);
        }

        $envelope = $record['response'] ?? null;
        $status = is_array($envelope) ? ($envelope['status_code'] ?? null) : null;
        $body = is_array($envelope) ? ($envelope['body'] ?? null) : null;
        if (!is_int($status) || !is_array($body)) {
            throw new RuntimeException('File batch record has no valid response envelope.');
        }
        if ($status < 200 || $status >= 300) {
            $failure = new BatchItemFailure(BatchItemFailureKind::ProviderError, "http_{$status}", 'Provider returned a failed item response.');
            return new BatchItemResult($key, Result::failure($failure), $provenance);
        }

        $synthetic = HttpResponse::sync($status, [], json_encode($body, JSON_THROW_ON_ERROR));
        $response = $this->responses->fromResponse($synthetic);
        if ($response === null) {
            throw new RuntimeException('File batch item response could not be decoded.');
        }

        return new BatchItemResult($key, Result::success($response), $provenance);
    }
}
