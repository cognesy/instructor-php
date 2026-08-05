<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

/**
 * The skeleton every request adapter shares: POST, the body from the body format, the stream
 * flag, and the telemetry correlation wrapper. Providers differ only in the URL they assemble
 * and the headers they send, which is what the two abstract methods are for.
 *
 * Before instructor-eexl.9 this method was copied verbatim into six adapters -- Anthropic,
 * Bedrock, Gemini, OpenAI, OpenAIResponses, OpenResponses -- differing between them by nothing
 * but brace placement. The four remaining adapters (Azure, CohereV2, GeminiOAI, HuggingFace)
 * already avoided the copy by extending OpenAIRequestAdapter, and still do: they are
 * OpenAI-compatible providers with different headers, and re-parenting them here would only
 * force them to re-declare the URL they are happy to inherit.
 *
 * Both hooks are abstract rather than defaulted to "{apiUrl}{endpoint}". Two of the six would
 * use such a default; the other four assemble a URL from a region, a model name or a fallback
 * endpoint. A silently plausible URL is a worse failure than a compile error.
 *
 * Tier B -- runs once per request, not per chunk. See 01-hot-path-invariants.md.
 */
abstract class BaseHttpRequestAdapter implements CanTranslateInferenceRequest
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) {}

    #[\Override]
    public function toHttpRequest(InferenceRequest $request): HttpRequest {
        $httpRequest = new HttpRequest(
            url: $this->toUrl($request),
            method: 'POST',
            headers: $this->toHeaders($request),
            body: $this->bodyFormat->toRequestBody($request),
            options: ['stream' => $request->isStreamed()],
        );

        return match ($request->telemetryCorrelation()) {
            null => $httpRequest,
            default => HttpRequestTelemetry::withCorrelation($httpRequest, $request->telemetryCorrelation()),
        };
    }

    // EXTENSION POINTS /////////////////////////////////////

    abstract protected function toUrl(InferenceRequest $request): string;

    abstract protected function toHeaders(InferenceRequest $request): array;
}
