<?php

namespace Cognesy\Instructor\Extras\LLM\Http;

use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

class GuzzleHttpClient implements CanHandleHttp
{
    use HandlesDebug;

    protected Client $client;

    public function __construct(
        protected LLMConfig $config,
    ) {
        if (isset($this->httpClient) && $this->config->debugEnabled()) {
            throw new InvalidArgumentException("Guzzle does not allow to inject debugging stack into existing client. Turn off debug or use default client.");
        }
        $this->client = match($this->config->debugEnabled()) {
            false => new Client(),
            true => new Client(['handler' => $this->addDebugStack(HandlerStack::create())]),
        };
    }

    public function handle(string $url, array $headers, array $body, bool $streaming = false) : ResponseInterface
    {
        return $this->client->post($url, [
            'headers' => $headers,
            'json' => $body,
            'connect_timeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
            'debug' => $this->config->debugHttpDetails() ?? false,
            'stream' => $streaming,
        ]);
    }
}