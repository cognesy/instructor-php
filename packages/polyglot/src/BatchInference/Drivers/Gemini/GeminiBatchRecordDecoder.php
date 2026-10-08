<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Gemini;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\BatchInference\Data\BatchItemFailure;
use Cognesy\Polyglot\BatchInference\Data\BatchItemProvenance;
use Cognesy\Polyglot\BatchInference\Data\BatchItemResult;
use Cognesy\Polyglot\BatchInference\Data\BatchManifest;
use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiUsageFormat;
use Cognesy\Utils\Result\Result;
use RuntimeException;

final readonly class GeminiBatchRecordDecoder
{
    private GeminiResponseAdapter $responses;

    public function __construct()
    {
        $this->responses = new GeminiResponseAdapter(new GeminiUsageFormat());
    }

    /** @param array<string, mixed> $record */
    public function decode(array $record, string $artifactId, int $ordinal, ?BatchManifest $manifest): BatchItemResult
    {
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $key = $record['key'] ?? $metadata['key'] ?? null;
        if (!is_string($key) || $key === '') {
            $key = $manifest?->keyAt($ordinal) ?? throw new RuntimeException('Gemini result has no key and no saved ordinal manifest.');
        }

        $provenance = new BatchItemProvenance($record, $artifactId, syntheticDecoderEnvelope: true);
        $error = is_array($record['error'] ?? null) ? $record['error'] : null;
        if ($error === null && isset($record['code']) && isset($record['message'])) {
            $error = $record;
        }
        if ($error !== null) {
            $code = (string) ($error['code'] ?? $error['status'] ?? 'provider_error');
            $failure = new BatchItemFailure(BatchItemFailureKind::ProviderError, $code, (string) ($error['message'] ?? ''));
            return new BatchItemResult($key, Result::failure($failure), $provenance);
        }

        $body = is_array($record['response'] ?? null) ? $record['response'] : $record;
        if (!is_array($body['candidates'] ?? null)) {
            throw new RuntimeException('Gemini batch success has no GenerateContentResponse.');
        }
        $response = $this->responses->fromResponse(HttpResponse::sync(200, [], json_encode($body, JSON_THROW_ON_ERROR)));
        if ($response === null) {
            throw new RuntimeException('Gemini batch response could not be decoded.');
        }
        return new BatchItemResult($key, Result::success($response), $provenance);
    }
}
