<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

interface CanTranslateInferenceResponse
{
    public function fromResponse(HttpResponse $response): ?InferenceResponse;
    public function fromStreamResponse(string $eventBody, ?HttpResponse $responseData = null): ?PartialInferenceResponse;
    public function toEventBody(string $data): string|bool;
}