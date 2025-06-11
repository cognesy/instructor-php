<?php

namespace Cognesy\Polyglot\Inference;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Polyglot\Inference\Drivers\InferenceDriverFactory;

/**
 * Inference class is facade for handling inference requests and responses.
 */
class Inference
{
    use HandlesEvents;

    use Traits\HandlesLLMProvider;
    use Traits\HandlesRequestBuilder;
    use Traits\HandlesInvocation;
    use Traits\HandlesShortcuts;

    /**
     * Constructor for initializing dependencies and configurations.
     */
    public function __construct(
        ?CanHandleEvents $events = null,
        ?CanProvideConfig $configProvider = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->requestBuilder = new InferenceRequestBuilder();
        $this->llmProvider = LLMProvider::new(
            $this->events,
            $configProvider,
        );
    }

    public static function registerDriver(string $name, string|callable $driver): void {
        InferenceDriverFactory::registerDriver($name, $driver);
    }
}
