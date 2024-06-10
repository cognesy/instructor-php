<?php
namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Traits\HandlesEventListeners;
use Cognesy\Instructor\Events\Traits\HandlesEvents;
use Saloon\Enums\Method;

abstract class ApiClient implements CanCallApi
{
    use HandlesEvents;
    use HandlesEventListeners;

    use Traits\HandlesApiConnector;
    use Traits\HandlesApiRequest;
    use Traits\HandlesApiRequestFactory;
    use Traits\HandlesApiResponse;
    use Traits\HandlesAsyncApiResponse;
    use Traits\HandlesDefaultMaxTokens;
    use Traits\HandlesDefaultModel;
    use Traits\HandlesQueryParams;
    use Traits\HandlesStreamApiResponse;
    use Traits\ReadsStreamResponse;

    public function __construct(
        EventDispatcher $events = null,
    ) {
        $this->withEventDispatcher($events ?? new EventDispatcher('api-client'));
    }

    /// PUBLIC API //////////////////////////////////////////////////////////////////////////////////////////

    public function request(
        array            $body,
        string           $endpoint = '',
        Method           $method = Method::POST,
        ApiRequestConfig $requestConfig = null,
        array            $data = [],
    ): static {
        $this->apiRequest = $this->apiRequestFactory->makeRequest($this->getModeRequestClass(), $body, $endpoint, $method, $data);
        return $this;
    }

    abstract public function getModeRequestClass(Mode $mode) : string;
}
