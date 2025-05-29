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
    use Traits\HandlesFluentMethods;
    use Traits\HandleInitMethods;
    use Traits\HandlesInvocation;
    use Traits\HandlesShortcuts;

    protected CachedContext $cachedContext;
    protected InferenceRequest $request;

    /**
     * Constructor for initializing dependencies and configurations.
     *
     * @param LLM|null $llm LLM object.
     * @param EventDispatcher|null $events Event dispatcher.
     *
     * @return void
     */
    public function __construct(
        ?LLM $llm = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->llm = $llm ?? new LLM(events: $this->events);
        $this->request = new InferenceRequest();
    }
}
