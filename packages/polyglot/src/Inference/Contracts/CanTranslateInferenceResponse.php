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
     * Tool-call correlation: implementations SHOULD give every delta that carries tool
     * data a non-empty $toolId that is stable for the lifetime of that tool call within
     * the stream, synthesising one from the provider's wire index when the wire supplies
     * none. All bundled adapters do this via ToolCallIdByStreamIndex.
     *
     * Implementations that cannot are still supported: InferenceStreamState falls back to
     * correlating by tool name and then by the tool currently in flight. That fallback is
     * necessarily weaker -- it cannot distinguish two interleaved calls to the same tool --
     * so supplying an id is preferred wherever the provider makes one derivable.
     *
     * A delta carrying a non-empty $toolId is treated as tool data even if name and args
     * are both empty, so do not mint ids for non-tool events.
     *
     * @return iterable<PartialInferenceDelta>
     */
    public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable;

    public function toEventBody(string $data): string|bool;
}
