<?php
namespace Cognesy\Instructor\Extras\LLM;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\InferenceRequested;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Data\DebugConfig;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use Cognesy\Instructor\Utils\Settings;

class Inference
{
    use Traits\HandlesDrivers;

    protected ?CanHandleHttp $httpClient = null;
    protected LLMConfig $config;
    protected CanHandleInference $driver;
    protected EventDispatcher $events;

    public function __construct(string $connection = '', EventDispatcher $events = null) {
        $this->events = $events ?? new EventDispatcher();
        $defaultConnection = $connection ?: Settings::get('llm', "defaultConnection");
        $this->config = LLMConfig::load($defaultConnection);
    }

    public static function forMessages(
        string|array $messages,
        string $connection = '',
        string $model = '',
        array $options = []
    ) : string {
        return (new Inference)
            ->withConnection($connection)
            ->withModel($model)
            ->create(
                messages: $messages,
                options: $options,
                mode: Mode::Text,
            )
            ->toText();
    }

    public function withConfig(LLMConfig $config): self {
        $this->config = $config;
        return $this;
    }

    public function withDebug(bool $debug = true) : self {
        $this->config->debug->enabled = $debug;
        return $this;
    }

    public function withDebugConfig(DebugConfig $config) : self {
        $this->config->debug = $config;
        return $this;
    }

    public function withConnection(string $connection): self {
        if (empty($connection)) {
            return $this;
        }
        $this->config = LLMConfig::load($connection);
        return $this;
    }

    public function withModel(string $model): self {
        if (empty($model)) {
            return $this;
        }
        $this->config->model = $model;
        return $this;
    }

    public function withDriver(CanHandleInference $driver): self {
        $this->driver = $driver;
        return $this;
    }

    public function withHttpClient(CanHandleHttp $httpClient): self {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function fromApiRequest(ApiRequest $apiRequest) : InferenceResponse {
        return $this->create(
            $apiRequest->messages(),
            $apiRequest->model(),
            $apiRequest->tools(),
            $apiRequest->toolChoice(),
            $apiRequest->responseFormat(),
            $apiRequest->options(),
            $apiRequest->mode()
        );
    }

    public function create(
        string|array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text
    ): InferenceResponse {
        $this->driver = $this->driver ?? $this->getDriver($this->config->clientType);
        $request = new InferenceRequest($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode);
        $this->events->dispatch(new InferenceRequested($request));
        return new InferenceResponse(
            response: $this->driver->handle($request),
            driver: $this->driver,
            config: $this->config,
            isStreamed: $options['stream'] ?? false
        );
    }
}
