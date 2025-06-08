<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\EventDispatcher;
use Cognesy\Events\EventHandlerFactory;
use Cognesy\Events\Traits\HandlesEventDispatching;
use Cognesy\Events\Traits\HandlesEventListening;
use Cognesy\Polyglot\LLM\Drivers\InferenceDriverFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Inference class is facade for handling inference requests and responses.
 */
class Inference
{
    use HandlesEventDispatching;
    use HandlesEventListening;

    use Traits\HandleLLMProvider;
    use Traits\HandlesRequestBuilder;
    use Traits\HandlesInvocation;
    use Traits\HandlesShortcuts;

    /**
     * Constructor for initializing dependencies and configurations.
     *
     * @param LLMProvider|null $llm LLM object.
     * @param EventDispatcher|null $events Event dispatcher.
     *
     * @return void
     */
    public function __construct(
        ?EventDispatcherInterface $events = null,
        ?EventDispatcherInterface $listener = null,
        ?CanProvideConfig         $configProvider = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();

        $this->requestBuilder = new InferenceRequestBuilder();
        $this->llmProvider = LLMProvider::new(
            $this->events,
            $this->listener,
            $configProvider,
        );
    }

    public static function registerDriver(string $name, string|callable $driver): void {
        InferenceDriverFactory::registerDriver($name, $driver);
    }
}
