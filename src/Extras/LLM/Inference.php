<?php
namespace Cognesy\Instructor\Extras\LLM;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Data\DebugConfig;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Drivers\AnthropicDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\AzureOpenAIDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\CohereDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\GeminiDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\MistralDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\OpenAICompatibleDriver;
use Cognesy\Instructor\Extras\LLM\Drivers\OpenAIDriver;
use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use Cognesy\Instructor\Utils\Settings;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Inference
{
    protected ?Client $client = null;
    protected LLMConfig $config;
    protected CanHandleInference $driver;

    public function __construct(string $connection = '') {
        $defaultConnection = $connection ?: Settings::get('llm', "defaultConnection");
        $this->config = LLMConfig::load($defaultConnection);
        //$this->client = $this->makeClient();
        //$this->driver = $this->getDriver($this->config->clientType);
    }

    public static function query(string $query, string $connection = '') : string {
        $instance = new Inference($connection);
        return $instance->create($query)->toText();
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
        $this->config = LLMConfig::load($connection);
        return $this;
    }

    public function withModel(string $model): self {
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
        if (is_string($messages)) {
            $messages = [['role' => 'user', 'content' => $messages]];
        }
        $this->driver = $this->driver ?? $this->getDriver($this->config->clientType);
        return new InferenceResponse(
            response: $this->driver->handle(new InferenceRequest($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode)),
            driver: $this->driver,
            config: $this->config,
            isStreamed: $options['stream'] ?? false
        );
    }

    // INTERNAL ///////////////////////////////////////

    protected function getDriver(ClientType $clientType): CanHandleInference {
        return match ($clientType) {
            ClientType::Anthropic => new AnthropicDriver($this->makeClient(), $this->config),
            ClientType::Azure => new AzureOpenAIDriver($this->makeClient(), $this->config),
            ClientType::Cohere => new CohereDriver($this->makeClient(), $this->config),
            ClientType::Fireworks => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            ClientType::Gemini => new GeminiDriver($this->makeClient(), $this->config),
            ClientType::Groq => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            ClientType::Mistral => new MistralDriver($this->makeClient(), $this->config),
            ClientType::Ollama => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            ClientType::OpenAI => new OpenAIDriver($this->makeClient(), $this->config),
            ClientType::OpenAICompatible => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            ClientType::OpenRouter => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            ClientType::Together => new OpenAICompatibleDriver($this->makeClient(), $this->config),
            default => throw new InvalidArgumentException("Client not supported: {$clientType->value}"),
        };
    }

    protected function addDebugStack(HandlerStack $stack) : HandlerStack {
        $stack->push(Middleware::tap(
            function (RequestInterface $request, $options) {
                $highlight = [Color::YELLOW];
                Console::println("[REQUEST]", $highlight);
                if ($this->config->debugSection('requestHeaders')) {
                    Console::println("[REQUEST HEADERS]", $highlight);
                    $this->printHeaders($request->getHeaders());
                    Console::println("[/REQUEST HEADERS]", $highlight);
                }
                if ($this->config->debugSection('requestBody')) {
                    Console::println("[REQUEST BODY]", $highlight);
                    dump(json_decode((string) $request->getBody()));
                    Console::println("[/REQUEST BODY]", $highlight);
                }
                Console::println("[/REQUEST]", $highlight);
                Console::println("");
                if ($this->config->debugHttpDetails()) {
                    Console::println("[HTTP DEBUG]", $highlight);
                }
            },
            function ($request, $options, FulfilledPromise $response) {
                $response->then(function (ResponseInterface $response) {
                    $highlight = [Color::YELLOW];
                    if ($this->config->debugHttpDetails()) {
                        Console::println("[/HTTP DEBUG]", $highlight);
                        Console::println("");
                    }
                    Console::println("[RESPONSE]", $highlight);
                    if ($this->config->debugSection('responseHeaders')) {
                        Console::println("[RESPONSE HEADERS]", $highlight);
                        $this->printHeaders($response->getHeaders());
                        Console::println("[/RESPONSE HEADERS]", $highlight);
                    }
                    if ($this->config->debugSection('responseBody')) {
                        Console::println("[RESPONSE BODY]", $highlight);
                        dump(json_decode((string) $response->getBody()));
                        Console::println("[/RESPONSE BODY]", $highlight);
                    }
                    Console::println("[/RESPONSE]", $highlight);
                    $response->getBody()->seek(0);
                });
            })
        );
        return $stack;
    }

    private function printHeaders(array $headers) {
        foreach ($headers as $name => $values) {
            Console::print("   ".$name, [Color::DARK_GRAY]);
            Console::print(': ', [Color::WHITE]);
            Console::println(implode(' | ', $values), [Color::GRAY]);
        }
    }

    private function makeClient() : Client {
        if (isset($this->client) && $this->config->debugEnabled()) {
            throw new InvalidArgumentException("Guzzle does not allow to inject debugging stack into existing client. Turn off debug or use default client.");
        }
        return match($this->config->debugEnabled()) {
            false => $this->client ?? new Client(),
            true => new Client(['handler' => $this->addDebugStack(HandlerStack::create())]),
        };
    }
}
