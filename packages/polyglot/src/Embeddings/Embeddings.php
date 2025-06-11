<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\EmbeddingsDriverFactory;

/**
 * Embeddings is a facade responsible for generating embeddings for provided input data
 */
class Embeddings
{
    use HandlesEvents;

    use Traits\HandlesInitMethods;
    use Traits\HandlesFluentMethods;
    use Traits\HandlesShortcuts;
    use Traits\HandlesInvocation;

    protected EmbeddingsProvider $embeddingsProvider;
    protected EmbeddingsRequest $request;

    public function __construct(
        ?CanHandleEvents $events = null,
        ?CanProvideConfig $configProvider = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->embeddingsProvider = EmbeddingsProvider::new(
            $this->events,
            $configProvider,
        );
    }

    public static function registerDriver(string $name, string|callable $driver) {
        EmbeddingsDriverFactory::registerDriver($name, $driver);
    }
}
