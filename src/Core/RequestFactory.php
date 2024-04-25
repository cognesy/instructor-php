<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Enums\Mode;

class RequestFactory
{
    public function __construct(
        protected ApiClientFactory $clientFactory,
    ) {}

    public function create(
        string|array $messages,
        string|object|array $responseModel,
        string $model = '',
        int $maxRetries = 0,
        array $options = [],
        string $functionName = 'extract_data',
        string $functionDescription = 'Extract data from provided content',
        string $retryPrompt = "Recall function correctly, fix following errors",
        Mode $mode = Mode::Tools,
    ) : Request {
        return new Request(
            $messages,
            $responseModel,
            $model,
            $maxRetries,
            $options,
            $functionName,
            $functionDescription,
            $retryPrompt,
            $mode,
            $this->clientFactory->getDefault(),
        );
    }

    public function fromRequest(
        Request $request,
    ) : Request {
        $request->client = $this->clientFactory->getDefault();
        return $request;
    }
}