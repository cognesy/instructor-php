<?php

namespace Cognesy\Instructor\Clients\OpenAIExt;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Data\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Events\EventDispatcher;
use Generator;

class OpenAIExt implements CanCallApi
{
    public string $defaultModel = 'gpt-3.5-turbo';

    protected EventDispatcher $events;
    protected ApiRequest $request;
    protected array $queryParams = [];
    /** @var class-string */
    protected string $responseClass;

    public function __construct(
        EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
    }

    public function respond(): ApiResponse {
        // TODO: Implement respond() method.
    }

    public function stream(): Generator {
        // TODO: Implement stream() method.
    }

    public function streamAll(): array {
        // TODO: Implement streamAll() method.
    }
}