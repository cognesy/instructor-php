<?php

namespace Cognesy\Instructor\Extras\LLM\Drivers\Traits;

use Cognesy\Instructor\Extras\LLM\InferenceRequest;
use Psr\Http\Message\ResponseInterface;

trait HandlesHttpClient
{
    public function handle(InferenceRequest $request) : ResponseInterface {
        return $this->client->post($this->getEndpointUrl($request), [
            'headers' => $this->getRequestHeaders(),
            'json' => $this->getRequestBody(
                $request->messages,
                $request->model,
                $request->tools,
                $request->toolChoice,
                $request->responseFormat,
                $request->options,
                $request->mode,
            ),
            'connect_timeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
            'debug' => $this->config->debugHttpDetails() ?? false,
        ]);
    }
}