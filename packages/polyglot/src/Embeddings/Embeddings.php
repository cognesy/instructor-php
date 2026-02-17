<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\HttpClient;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Contracts\CanResolveEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Drivers\EmbeddingsDriverFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Embeddings is a facade responsible for generating embeddings for provided input data
 */
class Embeddings implements CanCreateEmbeddings
{
    use HandlesEvents;

    use Traits\HandlesInitMethods;
    use Traits\HandlesFluentMethods;
    use Traits\HandlesShortcuts;
    use Traits\HandlesInvocation;

    protected EmbeddingsProvider $embeddingsProvider;
    protected ?CanResolveEmbeddingsConfig $embeddingsResolver = null;
    protected ?EmbeddingsRuntime $runtimeCache = null;
    protected bool $runtimeCacheDirty = true;
    /** @var HttpClient|null Facade-level HTTP client (optional) */
    protected ?HttpClient $httpClient = null;
    /** @var string|null Facade-level HTTP debug preset (optional) */
    protected ?string $httpDebugPreset = null;

    public function __construct(
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?CanProvideConfig $configProvider = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->embeddingsProvider = EmbeddingsProvider::new(
            $this->events,
            $configProvider,
        );
    }

    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): static {
        $copy = clone $this;
        $copy->events = EventBusResolver::using($events);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    /**
     * @param callable(Config\EmbeddingsConfig, HttpClient, EventDispatcherInterface): Contracts\CanHandleVectorization|string $driver
     */
    public static function registerDriver(string $name, string|callable $driver) : void {
        EmbeddingsDriverFactory::registerDriver($name, $driver);
    }

    protected function invalidateRuntimeCache(): void {
        $this->runtimeCache = null;
        $this->runtimeCacheDirty = true;
    }
}
