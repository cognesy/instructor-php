<?php
namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Contracts\CanCallLLM;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Traits\HandlesEventListeners;
use Cognesy\Instructor\Events\Traits\HandlesEvents;
use Cognesy\Instructor\Utils\Settings;

abstract class LLMClient implements CanCallLLM
{
    use HandlesEvents;
    use HandlesEventListeners;

    use Traits\HandlesApiConnector;
    use Traits\HandlesApiRequest;
    use Traits\HandlesApiRequestFactory;
    use Traits\HandlesApiResponse;
    use Traits\HandlesDebug;
    use Traits\HandlesDefaultMaxTokens;
    use Traits\HandlesDefaultModel;
    use Traits\HandlesQueryParams;
    use Traits\HandlesStreamApiResponse;
    use Traits\ReadsStreamResponse;

    protected ClientType $clientType;

    public function __construct(
        EventDispatcher $events = null,
    ) {
        $this->withEventDispatcher($events ?? new EventDispatcher('api-client'));
        $this->clientType = ClientType::fromClientClass(static::class);
        $this->withMaxTokens(Settings::get("connections.{$this->clientType->value}.defaultMaxTokens", 1024));
        $this->withModel(Settings::get("connections.{$this->clientType->value}.defaultModel", ''));
    }

    /// PUBLIC API //////////////////////////////////////////////////////////////////////////////////////////

    public function request(
        array            $body,
        string           $endpoint = '',
        string           $method = 'POST',
        ApiRequestConfig $requestConfig = null,
        array            $data = [],
    ): static {
        $clientType = $this->clientType;
        $mode = $data['mode'] ?? Mode::Json;
        $this->apiRequest = $this->apiRequestFactory->makeRequest(
            $clientType->toRequestClass($mode), $body, $endpoint, $method, $data
        );
        return $this;
    }
}
