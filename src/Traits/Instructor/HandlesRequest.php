<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\RequestInfo;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use JetBrains\PhpStorm\Deprecated;
use Throwable;

trait HandlesRequest
{
    private ResponseModelFactory $responseModelFactory;
    private Request $request;
    private RequestHandler $requestHandler;
    private RequestInfo $requestData;
    private array $cachedContext = [];
    private ?CanHandleInference $driver = null;
    private ?CanHandleHttp $httpClient = null;
    private string $connection;

    // PUBLIC /////////////////////////////////////////////////////////////////////

    public function getRequest() : Request {
        return $this->request;
    }

    /**
     * Prepares Instructor for execution with provided request data
     */
    public function withRequest(RequestInfo $requestData) : static {
        $this->requestData = $requestData;
        return $this;
    }

    public function withDriver(CanHandleInference $driver) : self {
        $this->driver = $driver;
        return $this;
    }

    public function withHttpClient(CanHandleHttp $httpClient) : self {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function withConnection(string $connection) : self {
        $this->connection = $connection;
        return $this;
    }

    #[Deprecated]
    public function withClient(string $client) : self {
        $this->connection = $client;
        return $this;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    protected function handleRequest() : mixed {
        $this->dispatchQueuedEvents();
        try {
            $this->request = $this->requestFromData(
                $this->requestData->withCachedContext($this->cachedContext)
            );
            return $this->requestHandler->responseFor($this->request);
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function handleStreamRequest() : Iterable {
        $this->dispatchQueuedEvents();
        try {
            $this->request = $this->requestFromData(
                $this->requestData->withCachedContext($this->cachedContext)
            );
            yield from $this->requestHandler->streamResponseFor($this->request);
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function requestFromData(
        RequestInfo $data,
    ) : Request {
        return new Request(
            messages: $data->messages ?? [],
            input: $data->input ?? [],
            responseModel: $data->responseModel ?? [],
            system: $data->system ?? '',
            prompt: $data->prompt ?? '',
            examples: $data->examples ?? [],
            model: $data->model ?? '',
            maxRetries: $data->maxRetries ?? 0,
            options: $data->options ?? [],
            toolName: $data->toolName ?? '',
            toolDescription: $data->toolDescription ?? '',
            retryPrompt: $data->retryPrompt ?? '',
            mode: $data->mode ?? Mode::Tools,
            cachedContext: $data->cachedContext ?? [],
            connection: $this->connection ?? '',
            httpClient: $this->httpClient,
            driver: $this->driver,
            events: $this->events,
        );
    }
}
