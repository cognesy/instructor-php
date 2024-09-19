<?php
namespace Cognesy\Instructor\Extras\LLM;

use GuzzleHttp\Client;
use InvalidArgumentException;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Settings;
use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

class LLM
{
    use Traits\HasConnectors;

    protected Client $client;
    protected ClientType $clientType;
    protected Mode $mode;

    protected int $connectTimeout;
    protected int $requestTimeout;

    protected string $apiUrl = '';
    protected string $apiKey = '';
    protected string $endpoint = '';
    protected array $metadata = [];

    protected array $body;
    protected array $headers;
    
    protected string $model = '';
    protected int $maxTokens;
    protected array $messages = [];

    protected array $tools;
    protected array $toolChoice;
    protected array $responseFormat;
    protected array $jsonSchema;
    protected string $schemaName;
    protected array $cachedContext;


    protected ApiRequest $apiRequest;

    public function __construct()
    {
        $this->client = new Client();
        $client = Settings::get('embed', "defaultConnection");
        $this->loadConfig($client);
    }

    public function withClient(string $client): self
    {
        $this->loadConfig($client);
        return $this;
    }

    // INTERNAL ///////////////////////////////////////

    protected function loadConfig(string $client) : void {
        if (!Settings::has('embed', "connections.$client")) {
            throw new InvalidArgumentException("Unknown client: $client");
        }
        $this->clientType = ClientType::from(Settings::get('llm', "connections.$client.clientType"));
        $this->apiKey = Settings::get('llm', "connections.$client.apiKey", '');
        $this->model = Settings::get('llm', "connections.$client.defaultModel", '');
        $this->metadata = Settings::get('llm', "connections.$client.metadata", 0);
        $this->maxTokens = Settings::get('llm', "connections.$client.defaultMaxTokens", 1024);
        $this->apiUrl = Settings::get('llm', "connections.$client.apiUrl");
        $this->endpoint = Settings::get('llm', "connections.$client.endpoint");
        $this->requestTimeout = Settings::get("llm", "connections.$client.requestTimeout", 3);
        $this->connectTimeout = Settings::get(group: "llm", key: "connections.$client.requestTimeout", default: 30);
    }
}
