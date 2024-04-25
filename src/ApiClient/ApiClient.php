<?php
namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Traits\HandlesEventListeners;
use Cognesy\Instructor\Events\Traits\HandlesEvents;

abstract class ApiClient implements CanCallApi
{
    use HandlesEvents;
    use HandlesEventListeners;
    use Traits\HandlesDefaultModel;
    use Traits\HandlesResponse;
    use Traits\HandlesAsyncResponse;
    use Traits\HandlesStreamResponse;
    use Traits\HandlesRequestClass;
    use Traits\HandlesApiRequestFactory;

    public function __construct(
        EventDispatcher $events = null,
    ) {
        $this->withEventDispatcher($events ?? new EventDispatcher());
    }

    protected function makeRequest(string $requestClass, array $args): ApiRequest {
        return $this->apiRequestFactory->fromClass($requestClass, $args);
    }
}