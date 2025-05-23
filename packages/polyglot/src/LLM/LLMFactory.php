<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\HttpClientFactory;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Psr\EventDispatcher\EventDispatcherInterface;

class LLMFactory
{
    private EventDispatcherInterface $events;
    private InferenceDriverFactory $driverFactory;
    private HttpClientFactory $httpDriverFactory;

    public function __construct(
        EventDispatcherInterface $events = null,
    ) {
        $this->events = $events;
        $this->driverFactory = new InferenceDriverFactory(
            events: $this->events
        );
    }

    public function using(string $preset): CanHandleInference {
        if (empty($preset)) {
            return $this->withConfig(LLMConfig::default());
        }
        return $this->withConfig(LLMConfig::load($preset));
    }

    public function withConfig(LLMConfig $config): CanHandleInference {
        if (!empty($config->httpClient)) {
            $httpClient = (new HttpClientFactory($this->events))->fromPreset($config->httpClient);
            $this->withHttpClient($httpClient);
        } else {
            $this->driver = $this->driverFactory->makeDriver($config);
        }
        return $this;
    }

    public function withHttpClient(CanHandleHttpRequest $httpClient): CanHandleInference {
        return $this->driverFactory->makeDriver($this->config, $httpClient);
    }
}