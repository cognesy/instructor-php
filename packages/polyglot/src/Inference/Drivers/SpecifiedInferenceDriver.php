<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers;

use Closure;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The one driver class behind every provider described by an {@see InferenceDriverSpec}.
 *
 * It adds exactly one thing to `BaseInferenceRequestDriver`: an answer to `capabilities()`
 * that comes from data instead of from an overridden method. Everything else -- the request
 * and response translation, the event gates, the error handling -- is the base class doing
 * what it already did for the twenty-six bundled classes this replaces.
 *
 * Deliberately does NOT touch usage. INVARIANT I2: usage must never be normalised to a
 * zero-valued object in a shared path, and this class is the most shared path in the package.
 * A `?? InferenceUsage::none()` added here for tidiness would allocate on every chunk of every
 * provider at once and undo 6c7bf364d wholesale. Usage handling stays where it is, in each
 * provider's own response adapter, which is the only place that knows the provider's predicate
 * for "this payload actually carried usage".
 *
 * NOT final, and that is the extension point: overriding one method of an OpenAI-compatible
 * driver used to mean extending `OpenAIDriver`, and this is where that now happens. Name the
 * subclass in {@see InferenceDriverSpec::$driverClass} and the spec assembles its collaborators
 * for it.
 */
class SpecifiedInferenceDriver extends BaseInferenceRequestDriver
{
    /** @param DriverCapabilities|(Closure(string): DriverCapabilities)|null $capabilities */
    public function __construct(
        LLMConfig $config,
        CanSendHttpRequests $httpClient,
        EventDispatcherInterface $events,
        CanTranslateInferenceRequest $requestTranslator,
        CanTranslateInferenceResponse $responseTranslator,
        private readonly DriverCapabilities|Closure|null $capabilities = null,
    ) {
        parent::__construct(
            config: $config,
            httpClient: $httpClient,
            events: $events,
            requestTranslator: $requestTranslator,
            responseTranslator: $responseTranslator,
        );
    }

    /**
     * The literal case returns the spec's own instance rather than a copy. That is safe only
     * because `DriverCapabilities` is a readonly class: the shared instance reaches every
     * driver built from a given spec, and the bundled specs live for the whole process.
     */
    #[\Override]
    public function capabilities(?string $model = null): DriverCapabilities {
        return match (true) {
            $this->capabilities instanceof Closure => ($this->capabilities)($model ?? $this->config->model),
            $this->capabilities instanceof DriverCapabilities => $this->capabilities,
            default => new DriverCapabilities(),
        };
    }
}
