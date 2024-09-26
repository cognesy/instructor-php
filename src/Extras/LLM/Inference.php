<?php
namespace Cognesy\Instructor\Extras\LLM;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Data\DebugConfig;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Utils\Settings;
use GuzzleHttp\Client;

class Inference
{
    use Traits\HandlesDebug;
    use Traits\HandlesDrivers;

    protected ?Client $client = null;
    protected LLMConfig $config;
    protected CanHandleInference $driver;

    public function __construct(string $connection = '') {
        $defaultConnection = $connection ?: Settings::get('llm', "defaultConnection");
        $this->config = LLMConfig::load($defaultConnection);
        //$this->client = $this->makeClient();
        //$this->driver = $this->getDriver($this->config->clientType);
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

    public function withClient(Client $client): self {
        $this->client = $client;
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
        return new InferenceResponse(
            response: $this->driver->handle(new InferenceRequest($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode)),
            driver: $this->driver,
            config: $this->config,
            isStreamed: $options['stream'] ?? false
        );
    }
}
