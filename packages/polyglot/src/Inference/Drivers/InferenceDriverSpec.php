<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers;

use Closure;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;

/**
 * Declarative construction data for an inference provider.
 *
 * The spec names every provider-specific translator and its capabilities. The same shape works
 * for OpenAI-compatible, native-protocol, and bespoke-HTTP providers; the request adapter is
 * where URL and header behavior belongs, rather than in a provider driver class.
 *
 * A spec is invokable, so `InferenceDriverRegistry` needs no special case: it already accepts
 * any `callable(LLMConfig, CanSendHttpRequests, CanHandleEvents): CanProcessInferenceRequest`,
 * and `withDriver()` continues to accept plain class-strings and closures exactly as before.
 * Both extension mechanisms stay supported; a spec is simply a third, declarative one.
 */
final readonly class InferenceDriverSpec
{
    /**
     * @param class-string $bodyFormat
     * @param class-string<CanTranslateInferenceRequest> $requestAdapter
     * @param class-string<CanTranslateInferenceResponse> $responseAdapter
     * @param class-string<CanMapUsage> $usageFormat
     * @param class-string<CanMapMessages> $messageFormat
     * @param DriverCapabilities|(Closure(string): DriverCapabilities)|null $capabilities
     *        A literal for the providers whose answer is fixed, which is all of them but one.
     *        Deepseek reports different capabilities for its reasoner models, so it needs a
     *        function of the model name -- the plan specified a plain `?DriverCapabilities`
     *        here, and that field cannot express Deepseek, which the plan also lists among the
     *        drivers to collapse. Widening the type was the smaller of the two deviations; the
     *        alternative was a bespoke class whose only bespoke part is six booleans.
     *        Null means the base default, i.e. everything supported.
     * @param class-string<SpecifiedInferenceDriver> $driverClass
     *        The extension point that replaces "subclass the bundled driver". Before this
     *        task, overriding one method of an OpenAI-compatible driver meant extending
     *        `OpenAIDriver`; that class is gone, so a subclass of `SpecifiedInferenceDriver`
     *        named here takes its place and still gets the five collaborators assembled for
     *        it. Custom class-strings and closures remain registrable directly, as before —
     *        this is a third option, not a replacement for either.
     */
    public function __construct(
        public string $bodyFormat,
        public string $requestAdapter = OpenAIRequestAdapter::class,
        public string $responseAdapter = OpenAIResponseAdapter::class,
        public string $usageFormat = OpenAIUsageFormat::class,
        public string $messageFormat = OpenAIMessageFormat::class,
        public DriverCapabilities|Closure|null $capabilities = null,
        public string $driverClass = SpecifiedInferenceDriver::class,
    ) {}

    /**
     * Plain `new $class(...)` in the same nesting order the hand-written constructors used --
     * no reflection and no container, so the cost is the four allocations the driver always
     * paid. Drivers are built per request; specs are built once, with the memoized registry.
     */
    public function __invoke(
        LLMConfig $config,
        CanSendHttpRequests $httpClient,
        CanHandleEvents $events,
    ): CanProcessInferenceRequest {
        return new ($this->driverClass)(
            config: $config,
            httpClient: $httpClient,
            events: $events,
            requestTranslator: new ($this->requestAdapter)(
                $config,
                new ($this->bodyFormat)(
                    $config,
                    new ($this->messageFormat)(),
                ),
            ),
            responseTranslator: new ($this->responseAdapter)(
                new ($this->usageFormat)(),
            ),
            capabilities: $this->capabilities,
        );
    }
}
