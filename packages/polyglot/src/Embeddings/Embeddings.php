<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Contracts\CanProvideEmbeddingsConfig;
use Cognesy\Utils\Events\EventHandlerFactory;
use Cognesy\Utils\Events\Traits\HandlesEventDispatching;
use Cognesy\Utils\Events\Traits\HandlesEventListening;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Embeddings is a facade responsible for generating embeddings for provided input data
 */
class Embeddings
{
    use HandlesEventDispatching;
    use HandlesEventListening;

    use Traits\HandlesInitMethods;
    use Traits\HandlesFluentMethods;
    use Traits\HandlesShortcuts;
    use Traits\HandlesInvocation;

    use Traits\HasFinders;

    protected EmbeddingsProvider $provider;
    protected EmbeddingsRequest $request;

    public function __construct(
        ?EventDispatcherInterface $events = null,
        ?EventDispatcherInterface $listener = null,
        ?CanProvideEmbeddingsConfig $configProvider = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();

        $this->request = new EmbeddingsRequest();
        $this->provider = new EmbeddingsProvider(
            $this->events,
            $this->listener,
            $configProvider,
        );
    }

    public static function registerDriver(string $name, string|callable $driver) {
        EmbeddingsDriverFactory::registerDriver($name, $driver);
    }
}
