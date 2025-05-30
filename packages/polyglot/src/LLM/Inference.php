<?php
namespace Cognesy\Polyglot\LLM;

use Cognesy\Polyglot\LLM\Data\CachedContext;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Events\Traits\HandlesEventDispatching;
use Cognesy\Utils\Events\Traits\HandlesEventListening;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Inference class is facade for handling inference requests and responses.
 */
class Inference
{
    use HandlesEventDispatching;
    use HandlesEventListening;

    use Traits\HandleInitMethods;
    use Traits\HandlesFluentMethods;
    use Traits\HandlesInvocation;
    use Traits\HandlesShortcuts;

    protected ?LLMProvider $llm;

    protected CachedContext $cachedContext;
    protected InferenceRequest $request;

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
        ?EventDispatcherInterface $listener = null
    ) {
        $default = (($events == null) || ($listener == null)) ? new EventDispatcher('inference') : null;
        $this->events = $events ?? $default;
        $this->listener = $listener ?? $default;

        $this->llm = new LLMProvider(
            $this->events,
            $this->listener,
        );
        $this->request = new InferenceRequest();
    }

    public static function registerDriver(string $name, string|callable $driver): void {
        InferenceDriverFactory::registerDriver($name, $driver);
    }
}
