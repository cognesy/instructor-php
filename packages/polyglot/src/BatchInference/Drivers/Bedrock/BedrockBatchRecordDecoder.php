<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Bedrock;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\BatchInference\Data\BatchItemFailure;
use Cognesy\Polyglot\BatchInference\Data\BatchItemProvenance;
use Cognesy\Polyglot\BatchInference\Data\BatchItemResult;
use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Utils\Result\Result;
use RuntimeException;

final readonly class BedrockBatchRecordDecoder
{
    private AnthropicResponseAdapter $responses;

    public function __construct()
    {
        $this->responses = new AnthropicResponseAdapter(new AnthropicUsageFormat());
    }

    /** @param array<string, mixed> $record */
    public function decode(array $record, string $artifactId): BatchItemResult
    {
        $key = $record['recordId'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new RuntimeException('Bedrock batch output has no recordId.');
        }
        $provenance = new BatchItemProvenance($record, $artifactId, $key, true);
        $error = $record['error'] ?? null;
        if (is_array($error)) {
            $code = (string) ($error['errorCode'] ?? 'provider_error');
            $message = is_string($error['errorMessage'] ?? null) ? $error['errorMessage'] : '';
            return new BatchItemResult($key, Result::failure(new BatchItemFailure(BatchItemFailureKind::ProviderError, $code, $message)), $provenance);
        }
        $body = $record['modelOutput'] ?? null;
        if (!is_array($body)) {
            throw new RuntimeException('Bedrock batch output has no modelOutput or error.');
        }
        $response = $this->responses->fromResponse(HttpResponse::sync(200, [], json_encode($body, JSON_THROW_ON_ERROR)));
        if ($response === null) {
            throw new RuntimeException('Bedrock batch model output could not be decoded.');
        }
        return new BatchItemResult($key, Result::success($response), $provenance);
    }
}
