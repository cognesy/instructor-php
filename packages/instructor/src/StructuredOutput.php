<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Instructor\Core\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Core\StructuredOutputRequestBuilder;
use Cognesy\Polyglot\Inference\LLMProvider;

/**
 * The StructuredOutput is facade for handling structured output requests and responses.
 */
class StructuredOutput
{
    use HandlesEvents;

    use Traits\HandlesLLMProvider;
    use Traits\HandlesInvocation;
    use Traits\HandlesShortcuts;
    use Traits\HandlesRequestBuilder;
    use Traits\HandlesConfigBuilder;

    use Traits\HandlesOverrides;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesSequenceUpdates;

    // CONSTRUCTORS ///////////////////////////////////////////////////////////

    public function __construct(
        ?CanHandleEvents          $events = null,
        ?CanProvideConfig         $configProvider = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->configBuilder = new StructuredOutputConfigBuilder(configProvider: $configProvider);
        $this->requestBuilder = new StructuredOutputRequestBuilder();
        $this->llmProvider = LLMProvider::new(
            events: $this->events,
            configProvider: $configProvider,
        );
    }
}