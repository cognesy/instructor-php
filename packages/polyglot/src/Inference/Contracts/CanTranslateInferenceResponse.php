<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

interface CanTranslateInferenceResponse
{
    public function fromResponse(HttpResponse $response): ?InferenceResponse;
    /** @return iterable<PartialInferenceResponse> */
    public function fromStreamResponses(iterable $eventBodies, ?HttpResponse $responseData = null): iterable;
    public function toEventBody(string $data): string|bool;
}
