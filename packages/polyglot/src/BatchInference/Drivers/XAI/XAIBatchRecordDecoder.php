<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\XAI;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\BatchInference\Data\BatchItemFailure;
use Cognesy\Polyglot\BatchInference\Data\BatchItemProvenance;
use Cognesy\Polyglot\BatchInference\Data\BatchItemResult;
use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Utils\Result\Result;
use RuntimeException;

final readonly class XAIBatchRecordDecoder
{
    /** @param array<string, mixed> $record */
    public function decode(array $record): BatchItemResult
    {
        $key = $record['batch_request_id'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new RuntimeException('xAI batch result has no batch_request_id.');
        }
        $provenance = new BatchItemProvenance($record, 'xai-results', syntheticDecoderEnvelope: true);
        $result = is_array($record['batch_result'] ?? null) ? $record['batch_result'] : [];
        $envelope = is_array($result['response'] ?? null) ? $result['response'] : [];
        $body = $envelope['chat_get_completion'] ?? null;
        if (is_array($body)) {
            $response = (new OpenAIResponseAdapter(new OpenAIUsageFormat()))
                ->fromResponse(HttpResponse::sync(200, [], json_encode($body, JSON_THROW_ON_ERROR)));
            if ($response === null) {
                throw new RuntimeException('xAI batch completion could not be decoded.');
            }
            return new BatchItemResult($key, Result::success($response), $provenance);
        }

        if ($envelope !== [] || (!is_array($result['error'] ?? null) && !is_string($record['error_message'] ?? null))) {
            throw new RuntimeException('xAI batch result has no supported chat completion or item error.');
        }

        $error = is_array($result['error'] ?? null) ? $result['error'] : [];
        $code = is_string($error['code'] ?? null) ? $error['code'] : 'provider_error';
        $message = is_string($record['error_message'] ?? null)
            ? $record['error_message']
            : (is_string($error['message'] ?? null) ? $error['message'] : 'xAI did not return a chat completion.');
        $kind = match (strtolower($code)) {
            'cancelled', 'canceled' => BatchItemFailureKind::Cancelled,
            'expired' => BatchItemFailureKind::Expired,
            default => BatchItemFailureKind::ProviderError,
        };
        return new BatchItemResult($key, Result::failure(new BatchItemFailure($kind, $code, $message)), $provenance);
    }
}
