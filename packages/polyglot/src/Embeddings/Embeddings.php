<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Utils\Events\EventDispatcher;
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
        ?EventDispatcherInterface $listener = null
    ) {
        $default = (($events == null) || ($listener == null)) ? new EventDispatcher('embeddings') : null;
        $this->events = $events ?? $default;
        $this->listener = $listener ?? $default;
        $this->request = new EmbeddingsRequest();
        $this->provider = new EmbeddingsProvider($this->events, $this->listener);
    }

    public static function registerDriver(string $name, string|callable $driver) {
        EmbeddingsDriverFactory::registerDriver($name, $driver);
    }
}
