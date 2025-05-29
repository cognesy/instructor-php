<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Traits\HasFinders;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Embeddings is a facade responsible for generating embeddings for provided input data
 */
class Embeddings
{
    use HasFinders;
    use Traits\HandlesFluentMethods;
    use Traits\HandlesShortcuts;
    use Traits\HandlesInitMethods;
    use Traits\HandlesInvocation;

    protected EventDispatcherInterface $events;
    protected EmbeddingsProviderFactory $embeddingsProviderFactory;

    protected EmbeddingsProvider $provider;
    protected EmbeddingsRequest $request;

    protected ?string $model = null;
    protected ?HttpClient $httpClient = null;

    public function __construct(
        string                $preset = '',
        EmbeddingsProvider    $provider = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->embeddingsProviderFactory = new EmbeddingsProviderFactory($this->events);
        $this->provider = $provider ?? $this->embeddingsProviderFactory->fromPreset($preset ?: Settings::get('embed', "defaultPreset"));
        $this->request = new EmbeddingsRequest();
    }
}
