<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

interface CanTranslateInferenceResponse
{
    public function fromResponse(HttpResponse $response): ?InferenceResponse;

    /**
     * Translates raw SSE event bodies into a stream of partial deltas.
     *
     * Each delta carries ordered assistant-message chunks. Providers must use a stable
     * chunk index for every block across the lifetime of the stream; tool-call ids are
     * provider data and are not used as a substitute for block identity.
     *
     * @return iterable<PartialInferenceDelta>
     */
    public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable;

    public function toEventBody(string $data): string|bool;
}
