Project Path: poly-tmp

Source Tree:

```
poly-tmp
├── Embeddings
│   ├── EmbeddingsResponse.php
│   ├── Drivers
│   │   └── OpenAIDriver.php
│   ├── Contracts
│   │   └── CanVectorize.php
│   ├── Traits
│   │   └── HasFinders.php
│   ├── Data
│   │   ├── EmbeddingsConfig.php
│   │   └── Vector.php
│   ├── Embeddings.php
│   ├── EmbeddingsRequest.php
│   └── EmbeddingsDriverFactory.php
└── LLM
    ├── InferenceResponse.php
    ├── Drivers
    │   ├── ModularLLMDriver.php
    │   └── OpenAI
    │       ├── OpenAIMessageFormat.php
    │       ├── OpenAIRequestAdapter.php
    │       ├── OpenAIDriver.php
    │       ├── OpenAIUsageFormat.php
    │       ├── OpenAIBodyFormat.php
    │       └── OpenAIResponseAdapter.php
    ├── InferenceStream.php
    ├── Contracts
    │   ├── CanMapMessages.php
    │   ├── CanHandleInference.php
    │   ├── CanMapUsage.php
    │   ├── CanMapRequestBody.php
    │   ├── ProviderRequestAdapter.php
    │   └── ProviderResponseAdapter.php
    ├── Enums
    │   ├── LLMProviderType.php
    │   ├── LLMFinishReason.php
    │   ├── OutputMode.php
    │   └── LLMContentType.php
    ├── InferenceRequest.php
    ├── Events
    │   ├── LLMResponseReceived.php
    │   ├── StreamDataParsed.php
    │   ├── PartialLLMResponseReceived.php
    │   ├── StreamDataReceived.php
    │   └── InferenceRequested.php
    ├── Inference.php
    ├── ModularDriverFactory.php
    ├── InferenceDriverFactory.php
    ├── Traits
    │   └── HandlesFluentMethods.php
    ├── EventStreamReader.php
    ├── LLM.php
    └── Data
        ├── LLMConfig.php
        ├── Usage.php
        ├── CachedContext.php
        ├── PartialLLMResponse.php
        ├── LLMResponse.php
        ├── ToolCalls.php
        └── ToolCall.php

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/Embeddings/EmbeddingsResponse.php`:

```php
<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\LLM\Data\Usage;

/**
 * EmbeddingsResponse represents the response from an embeddings request
 */
class EmbeddingsResponse
{
    public function __construct(
        /** @var Vector[] */
        public array $vectors,
        public ?Usage $usage,
    ) {}

    /**
     * Get the first vector
     * @return Vector
     */
    public function first() : Vector {
        return $this->vectors[0];
    }

    /**
     * Get the last vector
     * @return Vector
     */
    public function last() : Vector {
        return $this->vectors[count($this->vectors) - 1];
    }

    /**
     * Get all vectors
     * @return Vector[]
     */
    public function all() : array {
        return $this->vectors;
    }

    /**
     * Get the number of vectors
     * @return Usage
     */
    public function usage() : Usage {
        return $this->usage;
    }

    /**
     * Split the vectors into two EmbeddingsResponse objects
     * @param int $index
     * @return EmbeddingsResponse[]
     */
    public function split(int $index) : array {
        return [
            new EmbeddingsResponse(
                vectors: array_slice($this->vectors, 0, $index),
                usage: Usage::copy($this->usage()), // TODO: token split is arbitrary
            ),
            new EmbeddingsResponse(
                vectors: array_slice($this->vectors, $index),
                usage: new Usage(), // TODO: token split is arbitrary
            ),
        ];
    }

    /**
     * Get the values of all vectors
     * @return array
     */
    public function toValuesArray() : array {
        return array_map(
            fn(Vector $vector) => $vector->values(),
            $this->vectors
        );
    }

    /**
     * Get the total number of tokens
     * @return int
     */
    public function totalTokens() : int {
        return $this->usage()->total();
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/Embeddings/Drivers/OpenAIDriver.php`:

```php
<?php

namespace Cognesy\Polyglot\Embeddings\Drivers;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanVectorize;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;
use Cognesy\Polyglot\LLM\Data\Usage;
use Cognesy\Utils\Events\EventDispatcher;

class OpenAIDriver implements CanVectorize
{
    public function __construct(
        protected EmbeddingsConfig      $config,
        protected ?CanHandleHttpRequest $httpClient = null,
        protected ?EventDispatcher      $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->httpClient = $httpClient ?? HttpClient::make(events: $this->events);
    }

    public function vectorize(array $input, array $options = []): EmbeddingsResponse {
        $request = new HttpClientRequest(
            url: $this->getEndpointUrl(),
            method: 'POST',
            headers: $this->getRequestHeaders(),
            body: $this->getRequestBody($input, $options),
            options: [],
        );
        $response = $this->httpClient->handle($request);
        return $this->toResponse(json_decode($response->body(), true));
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function getEndpointUrl(): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }

    protected function getRequestHeaders(): array {
        return [
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ];
    }

    protected function getRequestBody(array $input, array $options) : array {
        return array_filter(array_merge([
            'input' => $input,
            'model' => $this->config->model,
            'encoding_format' => 'float',
        ], $options));
    }

    protected function toResponse(array $response) : EmbeddingsResponse {
        return new EmbeddingsResponse(
            vectors: array_map(
                callback: fn($item) => new Vector(values: $item['embedding'], id: $item['index']),
                array: $response['data']
            ),
            usage: $this->toUsage($response),
        );
    }

    protected function toUsage(array $response) : Usage {
        return new Usage(
            inputTokens: $response['usage']['prompt_tokens'] ?? 0,
            outputTokens: ($response['usage']['total_tokens'] ?? 0) - ($response['usage']['prompt_tokens'] ?? 0),
        );
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/Embeddings/Contracts/CanVectorize.php`:

```php
<?php
namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;

/**
 * Interface CanVectorize
 *
 * Defines the contract for embedding generation services
 */
interface CanVectorize
{
    /**
     * Generate embeddings for the input
     *
     * @param array<string> $input
     * @param array $options
     * @return EmbeddingsResponse
     */
    public function vectorize(array $input, array $options = []) : EmbeddingsResponse;
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/Embeddings/Traits/HasFinders.php`:

```php
<?php
namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Polyglot\Embeddings\Data\Vector;

/**
 * Trait HasFinders
 *
 * Provides methods for finding similar documents
 */
trait HasFinders
{
    /**
     * Find the most similar documents to the query
     * @param string $query
     * @param array $documents
     * @param int $topK
     * @return array
     */
    public function findSimilar(string $query, array $documents, int $topK = 5) : array {
        // generate embeddings for query and documents (in a single request)
        [$queryVector, $docVectors] = $this->create(array_merge([$query], $documents))->split(1);

        $docVectors = $docVectors->toValuesArray();
        $queryVector = $queryVector->first()?->values()
            ?? throw new \InvalidArgumentException('Query vector not found');

        $matches = self::findTopK($queryVector, $docVectors, $topK);
        return array_map(fn($i) => [
            'content' => $documents[$i],
            'similarity' => $matches[$i],
        ], array_keys($matches));
    }

    /**
     * Find the top K most similar documents to the query vector
     * @param array $queryVector
     * @param array $documentVectors
     * @param int $n
     * @return array
     */
    public static function findTopK(array $queryVector, array $documentVectors, int $n = 5) : array {
        $similarity = [];
        foreach ($documentVectors as $i => $vector) {
            $similarity[$i] = Vector::cosineSimilarity($queryVector, $vector);
        }
        arsort($similarity);
        return array_slice($similarity, 0, $n, true);
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/Embeddings/Data/EmbeddingsConfig.php`:

```php
<?php

namespace Cognesy\Polyglot\Embeddings\Data;

use Cognesy\Utils\Dsn\DSN;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

class EmbeddingsConfig
{
    public function __construct(
        public string $apiUrl = '',
        public string $apiKey = '',
        public string $endpoint = '',
        public string $model = '',
        public int $dimensions = 0,
        public int $maxInputs = 0,
        public array $metadata = [],
        public string $httpClient = '',
        public string $providerType = 'openai',
    ) {}

    public static function default() : EmbeddingsConfig {
        $default = Settings::get('embed', "defaultConnection", null);
        if (is_null($default)) {
            throw new InvalidArgumentException("No default connection found in settings.");
        }
        return self::load($default);
    }

    public static function load(string $connection) : EmbeddingsConfig {
        if (!Settings::has('embed', "connections.$connection")) {
            throw new InvalidArgumentException("Unknown connection: $connection");
        }

        return new EmbeddingsConfig(
            apiUrl: Settings::get('embed', "connections.$connection.apiUrl"),
            apiKey: Settings::get('embed', "connections.$connection.apiKey", ''),
            endpoint: Settings::get('embed', "connections.$connection.endpoint"),
            model: Settings::get('embed', "connections.$connection.defaultModel", ''),
            dimensions: Settings::get('embed', "connections.$connection.defaultDimensions", 0),
            maxInputs: Settings::get('embed', "connections.$connection.maxInputs", 1),
            metadata: Settings::get('embed', "connections.$connection.metadata", []),
            httpClient: Settings::get('embed', "connections.$connection.httpClient", ''),
            providerType: Settings::get('embed', "connections.$connection.providerType", 'openai'),
        );
    }

    public static function fromArray(array $value) : EmbeddingsConfig {
        return new static(
            apiUrl: $value['apiUrl'] ?? $value['api_url'] ?? '',
            apiKey: $value['apiKey'] ?? $value['api_key'] ?? '',
            endpoint: $value['endpoint'] ?? '',
            model: $value['model'] ?? '',
            dimensions: $value['dimensions'] ?? 0,
            maxInputs: $value['maxInputs'] ?? $value['max_inputs'] ?? 1,
            metadata: $value['metadata'] ?? [],
            httpClient: $value['httpClient'] ?? $value['http_client'] ?? '',
            providerType: $value['providerType'] ?? $value['provider'] ?? 'openai',
        );
    }

    public static function fromDSN(string $dsn) : EmbeddingsConfig {
        $data = DSN::fromString($dsn)->params();
        $connection = $data['connection'] ?? '';
        return match(true) {
            !empty($connection) => self::withOverrides(self::load($connection), $data),
            default => self::fromArray($data),
        };
    }

    private static function withOverrides(EmbeddingsConfig $config, array $overrides) : EmbeddingsConfig {
        $config->apiUrl = $overrides['apiUrl'] ?? $overrides['api_url'] ?? $config->apiUrl;
        $config->apiKey = $overrides['apiKey'] ?? $overrides['api_key'] ?? $config->apiKey;
        $config->endpoint = $overrides['endpoint'] ?? $config->endpoint;
        $config->model = $overrides['model'] ?? $config->model;
        $config->dimensions = $overrides['dimensions'] ?? $config->dimensions;
        $config->maxInputs = $overrides['maxInputs'] ?? $overrides['max_inputs'] ?? $config->maxInputs;
        $config->metadata = $overrides['metadata'] ?? $config->metadata;
        $config->httpClient = $overrides['httpClient'] ?? $overrides['http_client'] ?? $config->httpClient;
        $config->providerType = $overrides['providerType'] ?? $overrides['provider'] ?? $config->providerType;
        return $config;
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/Embeddings/Data/Vector.php`:

```php
<?php

namespace Cognesy\Polyglot\Embeddings\Data;

/**
 * Class Vector
 *
 * Represents an embedding - vector of floating point values
 */
class Vector
{
    public const METRIC_COSINE = 'cosine';
    public const METRIC_EUCLIDEAN = 'euclidean';
    public const METRIC_DOT_PRODUCT = 'dot_product';

    public function __construct(
        /** @var float[] */
        private array $values,
        private int|string $id = 0,
    ) {}

    /**
     * Get the vector values
     * @return float[]
     */
    public function values() : array {
        return $this->values;
    }

    /**
     * Get the vector ID
     * @return int|string
     */
    public function id() : int|string {
        return $this->id;
    }

    /**
     * Compare this vector to another vector using a metric
     * @param Vector $vector
     * @param string $metric
     * @return float
     */
    public function compareTo(Vector $vector, string $metric) : float {
        return match ($metric) {
            self::METRIC_COSINE => self::cosineSimilarity($this->values, $vector->values),
            self::METRIC_EUCLIDEAN => self::euclideanDistance($this->values, $vector->values),
            self::METRIC_DOT_PRODUCT => self::dotProduct($this->values, $vector->values),
            default => throw new \InvalidArgumentException("Unknown metric: $metric")
        };
    }

    /**
     * Calculate the cosine similarity between two vectors
     * @param float[] $v1
     * @param float[] $v2
     */
    public static function cosineSimilarity(array $v1, array $v2) : float {
        $dotProduct = 0.0;
        $magnitudeV1 = 0.0;
        $magnitudeV2 = 0.0;
        $count = count($v1);
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $v1[$i] * $v2[$i];
            $magnitudeV1 += $v1[$i] ** 2;
            $magnitudeV2 += $v2[$i] ** 2;
        }
        $magnitudeV1 = sqrt($magnitudeV1);
        $magnitudeV2 = sqrt($magnitudeV2);
        return $dotProduct / ($magnitudeV1 * $magnitudeV2);
    }

    /**
     * Calculate the Euclidean distance between two vectors
     * @param float[] $v1
     * @param float[] $v2
     */
    public static function euclideanDistance(array $v1, array $v2) : float {
        $sum = 0;
        $count = count($v1);
        for ($i = 0; $i < $count; $i++) {
            $sum += ($v1[$i] - $v2[$i]) ** 2;
        }
        return sqrt($sum);
    }

    /**
     * Calculate the dot product between two vectors
     * @param float[] $v1
     * @param float[] $v2
     */
    public static function dotProduct(array $v1, array $v2) : float {
        $sum = 0;
        $count = count($v1);
        for ($i = 0; $i < $count; $i++) {
            $sum += $v1[$i] * $v2[$i];
        }
        return $sum;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/Embeddings/Embeddings.php`:

```php
<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanVectorize;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Traits\HasFinders;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

/**
 * Embeddings is a facade responsible for generating embeddings for provided input data
 */
class Embeddings
{
    use HasFinders;

    protected EventDispatcher $events;
    protected EmbeddingsConfig $config;
    protected CanHandleHttpRequest $httpClient;
    protected CanVectorize $driver;
    protected EmbeddingsDriverFactory $driverFactory;
    private EmbeddingsRequest $request;

    public function __construct(
        string               $connection = '',
        ?EmbeddingsConfig     $config = null,
        ?CanHandleHttpRequest $httpClient = null,
        ?CanVectorize         $driver = null,
        ?EventDispatcher      $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $connection = $connection ?: Settings::get('embed', "defaultConnection");
        $this->config = $config ?? EmbeddingsConfig::load($connection);
        $this->httpClient = $httpClient ?? HttpClient::make(client: $this->config->httpClient, events: $this->events);
        $this->driverFactory = new EmbeddingsDriverFactory($this->events);
        $this->driver = $driver ?? $this->driverFactory->makeDriver($this->config, $this->httpClient);
        $this->request = new EmbeddingsRequest();
    }

    // PUBLIC static ////////////////////////////////////////////

    public static function registerDriver(string $name, string|callable $driver) {
        EmbeddingsDriverFactory::registerDriver($name, $driver);
    }

    public static function connection(string $connection = ''): self {
        return new self(connection: $connection);
    }

    public static function fromDSN(string $dsn): self {
        return new self(config: EmbeddingsConfig::fromDSN($dsn));
    }

    // PUBLIC ///////////////////////////////////////////////////

    /**
     * Configures the Embeddings instance with the given connection name.
     * @param string $connection
     * @return $this
     */
    public function withConnection(string $connection) : self {
        $this->config = EmbeddingsConfig::load($connection);
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given configuration.
     * @param EmbeddingsConfig $config
     * @return $this
     */
    public function withConfig(EmbeddingsConfig $config) : self {
        $this->config = $config;
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given model name.
     * @param string $model
     * @return $this
     */
    public function withModel(string $model) : self {
        $this->config->model = $model;
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given HTTP client.
     *
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $httpClient
     * @return $this
     */
    public function withHttpClient(CanHandleHttpRequest $httpClient) : self {
        $this->httpClient = $httpClient;
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given driver.
     * @param CanVectorize $driver
     * @return $this
     */
    public function withDriver(CanVectorize $driver) : self {
        $this->driver = $driver;
        return $this;
    }

    public function withInput(string|array $input) : self {
        $this->request->withAnyInput($input);
        return $this;
    }

    /**
     * Generates embeddings for the provided input data.
     * @param string|array $input
     * @param array $options
     * @return EmbeddingsResponse
     */
    public function create(
        string|array $input = [],
        array $options = []
    ) : EmbeddingsResponse {
        $input = $input ?: $this->request->inputs();
        if (empty($input)) {
            throw new InvalidArgumentException("Input data is required");
        }

        $options = array_merge($this->request->options(), $options);

        if (count($this->request->inputs()) > $this->config->maxInputs) {
            throw new InvalidArgumentException("Number of inputs exceeds the limit of {$this->config->maxInputs}");
        }

        return $this->driver->vectorize($input, $options);
    }

    /**
     * Enable or disable debugging for the current instance.
     *
     * @param bool $debug Whether to enable debug mode. Default is true.
     *
     * @return self
     */
    public function withDebug(bool $debug = true) : self {
        // TODO: it assumes we're using HttpClient class as a driver
        $this->httpClient->withDebug($debug);
        return $this;
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/Embeddings/EmbeddingsRequest.php`:

```php
<?php

namespace Cognesy\Polyglot\Embeddings;

class EmbeddingsRequest
{
    protected array $inputs = [];
    protected array $options = [];

    public function __construct(
        string|array $input = [],
        array $options = [],
    ) {
        $this->inputs = match(true) {
            is_string($input) => [$input],
            is_array($input) => $input,
            default => []
        };
        $this->options = $options;
    }

    public function withAnyInput(array|string $input) : static {
        $this->inputs = match(true) {
            is_string($input) => [$input],
            is_array($input) => $input,
            default => []
        };
        return $this;
    }

    public function withInput(string $input) : static {
        $this->inputs = [$input];
        return $this;
    }

    public function withInputs(array $inputs) : static {
        $this->inputs = $inputs;
        return $this;
    }

    public function withOptions(array $options) : static {
        $this->options = $options;
        return $this;
    }

    public function inputs() : array {
        return $this->inputs;
    }

    public function options() : array {
        return $this->options;
    }

    public function toArray() : array {
        return [
            'inputs' => $this->inputs,
            'options' => $this->options,
        ];
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/Embeddings/EmbeddingsDriverFactory.php`:

```php
<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Polyglot\Embeddings\Contracts\CanVectorize;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Drivers\AzureOpenAIDriver;
use Cognesy\Polyglot\Embeddings\Drivers\CohereDriver;
use Cognesy\Polyglot\Embeddings\Drivers\GeminiDriver;
use Cognesy\Polyglot\Embeddings\Drivers\JinaDriver;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAIDriver;
use Cognesy\Utils\Events\EventDispatcher;
use InvalidArgumentException;

class EmbeddingsDriverFactory
{
    protected static array $drivers = [];

    protected EventDispatcher $events;

    public function __construct(
        EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
    }

    public static function registerDriver(string $name, string|callable $driver) {
        self::$drivers[$name] = match(true) {
            is_string($driver) => fn($config, $httpClient, $events) => new $driver($config, $httpClient, $events),
            is_callable($driver) => $driver,
        };
    }

    /**
     * Returns the driver for the specified configuration.
     *
     * @param EmbeddingsConfig $config
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $httpClient
     * @return CanVectorize
     */
    public function makeDriver(EmbeddingsConfig $config, CanHandleHttpRequest $httpClient) : CanVectorize {
        $type = $config->providerType ?? 'openai';
        $driver = self::$drivers[$type] ?? $this->getBundledDriver($type);
        if (!$driver) {
            throw new InvalidArgumentException("Unknown driver: {$type}");
        }
        return $driver($config, $httpClient, $this->events);
    }

    protected function getBundledDriver(string $type) : ?callable {
        return match ($type) {
            'azure' => fn($config, $httpClient, $events) => new AzureOpenAIDriver($config, $httpClient, $events),
            'cohere1' => fn($config, $httpClient, $events) => new CohereDriver($config, $httpClient, $events),
            'cohere2' => fn($config, $httpClient, $events) => new CohereDriver($config, $httpClient, $events),
            'gemini' => fn($config, $httpClient, $events) => new GeminiDriver($config, $httpClient, $events),
            'mistral' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'openai' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'ollama' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'jina' => fn($config, $httpClient, $events) => new JinaDriver($config, $httpClient, $events),
            default => null,
        };
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/InferenceResponse.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Events\LLMResponseReceived;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Json\Json;
use InvalidArgumentException;

/**
 * Represents an inference response handling object that processes responses
 * based on the configuration and streaming state. Provides methods to
 * retrieve the response in different formats.
 */
class InferenceResponse
{
    protected EventDispatcher $events;
    protected HttpClientResponse $response;
    protected CanHandleInference $driver;
    protected string $responseContent = '';
    protected LLMConfig $config;
    protected bool $isStreamed = false;

    public function __construct(
        HttpClientResponse    $response,
        CanHandleInference $driver,
        LLMConfig          $config,
        bool               $isStreamed = false,
        ?EventDispatcher   $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->driver = $driver;
        $this->config = $config;
        $this->isStreamed = $isStreamed;
        $this->response = $response;
    }

    /**
     * Determines whether the content is streamed.
     *
     * @return bool True if the content is being streamed, false otherwise.
     */
    public function isStreamed() : bool {
        return $this->isStreamed;
    }

    /**
     * Converts the response to its text representation.
     *
     * @return string The textual representation of the response. If streaming, retrieves the final content; otherwise, retrieves the standard content.
     */
    public function toText() : string {
        return match($this->isStreamed) {
            false => $this->makeLLMResponse()->content(),
            true => $this->stream()->final()?->content() ?? '',
        };
    }

    /**
     * Converts the response content to a JSON representation.
     *
     * @return array The JSON representation of the content as an associative array.
     */
    public function asJsonData() : array {
        return Json::fromString($this->toText())->toArray();
    }

    /**
     * Initiates and returns an inference stream for the response.
     *
     * @return InferenceStream The initialized inference stream.
     * @throws InvalidArgumentException If the response is not configured for streaming.
     */
    public function stream() : InferenceStream {
        if (!$this->isStreamed) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }
        return new InferenceStream(
            response: $this->response,
            driver: $this->driver,
            config: $this->config,
            events: $this->events
        );
    }

    // AS API RESPONSE OBJECT ///////////////////////////////////

    /**
     * Generates and returns an LLMResponse based on the streaming status.
     *
     * @return LLMResponse The constructed LLMResponse object, either fully or from partial responses if streaming is enabled.
     */
    public function response() : LLMResponse {
        $response = match($this->isStreamed) {
            false => $this->makeLLMResponse(),
            true => LLMResponse::fromPartialResponses($this->stream()->all()),
        };
        return $response;
    }

    // INTERNAL /////////////////////////////////////////////////

    /**
     * Processes and generates a response from the Language Learning Model (LLM) driver.
     *
     * @return LLMResponse The generated response from the LLM driver.
     */
    private function makeLLMResponse() : LLMResponse {
        $content = $this->getResponseContent();
        $data = Json::decode($content) ?? [];
        $response = $this->driver->fromResponse($data);
        $this->events->dispatch(new LLMResponseReceived($response));
        return $response;
    }

    // PRIVATE /////////////////////////////////////////////////

    /**
     * Retrieves the content of the response. If the content has not been
     * set, it reads and initializes the content from the response.
     *
     * @return string The content of the response.
     */
    private function getResponseContent() : string {
        if (empty($this->responseContent)) {
            $this->responseContent = $this->readFromResponse();
        }
        return $this->responseContent;
    }

    /**
     * Reads and retrieves the contents from the response.
     *
     * @return string The contents of the response.
     */
    private function readFromResponse() : string {
        return $this->response->body();
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Drivers/ModularLLMDriver.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Drivers;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Utils\Events\EventDispatcher;

/**
 * ModularLLMDriver is responsible for handling inference requests and managing
 * the interaction between request/response adapters and an HTTP client for
 * communication with a large language model (LLM) backend.
 *
 * This class implements CanHandleInference interface, providing methods
 * to handle inference requests, convert responses from the LLM backend,
 * and manage streaming responses where applicable.
 */
class ModularLLMDriver implements CanHandleInference {
    public function __construct(
        protected LLMConfig               $config,
        protected ProviderRequestAdapter  $requestAdapter,
        protected ProviderResponseAdapter $responseAdapter,
        protected ?CanHandleHttpRequest   $httpClient = null,
        protected ?EventDispatcher        $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->httpClient = $httpClient ?? HttpClient::make(events: $this->events);
    }

    /**
     * Processes the given inference request and handles it through the HTTP client.
     *
     * @param \Cognesy\Polyglot\LLM\InferenceRequest $request The request to be processed, including messages, model, tools, and other parameters.
     * @return HttpClientResponse The response indicating the access result after processing the request.
     */
    public function handle(InferenceRequest $request): HttpClientResponse {
        $request = $request->withCacheApplied();
        $clientRequest = $this->requestAdapter->toHttpClientRequest(
            $request->messages(),
            $request->model(),
            $request->tools(),
            $request->toolChoice(),
            $request->responseFormat(),
            $request->options(),
            $request->outputMode(),
        );
        return $this->httpClient->handle(
            (new HttpClientRequest(
                url: $clientRequest->url(),
                method: $clientRequest->method(),
                headers: $clientRequest->headers(),
                body: $clientRequest->body()->toArray(),
                options: $clientRequest->options(),
            ))->withStreaming($clientRequest->isStreamed())
        );
    }

    /**
     * Converts response data (array decoded from JSON)
     * into an LLMResponse object using the response adapter.
     *
     * @param array $data The response data to be converted.
     * @return \Cognesy\Polyglot\LLM\Data\LLMResponse|null The converted LLMResponse object or null if conversion fails.
     */
    public function fromResponse(array $data): ?LLMResponse {
        return $this->responseAdapter->fromResponse($data);
    }

    /**
     * Processes stream response data (array decoded from JSON)
     * and converts it into a PartialLLMResponse object.
     *
     * @param array $data An array containing the stream response data to process.
     * @return \Cognesy\Polyglot\LLM\Data\PartialLLMResponse|null The converted PartialLLMResponse object, or null if the conversion is not possible.
     */
    public function fromStreamResponse(array $data): ?PartialLLMResponse {
        return $this->responseAdapter->fromStreamResponse($data);
    }

    /**
     * Processes raw stream data and converts it to data string
     * using the response adapter.
     *
     * False is returned if there's no data to process.
     *
     * @param string $data A string containing the stream data to process.
     * @return string|bool The processed data as a string, or false if there's no data to process.
     */
    public function fromStreamData(string $data): string|bool {
        return $this->responseAdapter->fromStreamData($data);
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Drivers/OpenAI/OpenAIMessageFormat.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;

class OpenAIMessageFormat implements CanMapMessages
{
    public function map(array $messages) : array {
        $list = [];
        foreach ($messages as $message) {
            $nativeMessage = $this->mapMessage($message);
            if (empty($nativeMessage)) {
                continue;
            }
            $list[] = $nativeMessage;
        }
        return $list;
    }

    protected function mapMessage(array $message) : array {
        return match(true) {
            ($message['role'] ?? '') === 'assistant' && !empty($message['_metadata']['tool_calls'] ?? []) => $this->toNativeToolCall($message),
            ($message['role'] ?? '') === 'tool' => $this->toNativeToolResult($message),
            default => $message,
        };
    }

    protected function toNativeToolCall(array $message) : array {
        return [
            'role' => 'assistant',
            'tool_calls' => $message['_metadata']['tool_calls'] ?? [],
        ];
    }

    protected function toNativeToolResult(array $message) : array {
        return [
            'role' => 'tool',
            'tool_call_id' => $message['_metadata']['tool_call_id'] ?? '',
            'content' => $message['content'] ?? '',
        ];
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Drivers/OpenAI/OpenAIRequestAdapter.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class OpenAIRequestAdapter implements ProviderRequestAdapter
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) {}

    public function toHttpClientRequest(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        OutputMode $mode
    ): HttpClientRequest {
        return new HttpClientRequest(
            url: $this->toUrl($model, $options['stream'] ?? false),
            method: 'POST',
            headers: $this->toHeaders(),
            body: $this->bodyFormat->map($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode),
            options: ['stream' => $options['stream'] ?? false],
        );
    }

    protected function toHeaders(): array {
        $extras = array_filter([
            "OpenAI-Organization" => $this->config->metadata['organization'] ?? '',
            "OpenAI-Project" => $this->config->metadata['project'] ?? '',
        ]);
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $extras);
    }

    protected function toUrl(string $model = '', bool $stream = false): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Drivers/OpenAI/OpenAIDriver.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Utils\Events\EventDispatcher;

class OpenAIDriver implements CanHandleInference
{
    protected ProviderRequestAdapter  $requestAdapter;
    protected ProviderResponseAdapter $responseAdapter;

    public function __construct(
        protected LLMConfig               $config,
        protected ?CanHandleHttpRequest   $httpClient = null,
        protected ?EventDispatcher        $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->httpClient = $httpClient ?? HttpClient::make(events: $this->events);
        $this->requestAdapter = new OpenAIRequestAdapter(
            $config,
            new OpenAIBodyFormat(
                $config,
                new OpenAIMessageFormat(),
            )
        );
        $this->responseAdapter = new OpenAIResponseAdapter(
            new OpenAIUsageFormat()
        );
    }

    public function handle(InferenceRequest $request): HttpClientResponse
    {
        $request = $request->withCacheApplied();
        $clientRequest = $this->requestAdapter->toHttpClientRequest(
            $request->messages(),
            $request->model(),
            $request->tools(),
            $request->toolChoice(),
            $request->responseFormat(),
            $request->options(),
            $request->outputMode(),
        );
        return $this->httpClient->handle(
            (new HttpClientRequest(
                url: $clientRequest->url(),
                method: $clientRequest->method(),
                headers: $clientRequest->headers(),
                body: $clientRequest->body()->toArray(),
                options: $clientRequest->options(),
            ))->withStreaming($clientRequest->isStreamed())
        );
    }

    public function fromResponse(array $data): ?LLMResponse
    {
        return $this->responseAdapter->fromResponse($data);
    }

    public function fromStreamResponse(array $data): ?PartialLLMResponse
    {
        return $this->responseAdapter->fromStreamResponse($data);
    }

    public function fromStreamData(string $data): string|bool
    {
        return $this->responseAdapter->fromStreamData($data);
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Drivers/OpenAI/OpenAIUsageFormat.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

use Cognesy\Polyglot\LLM\Contracts\CanMapUsage;
use Cognesy\Polyglot\LLM\Data\Usage;

class OpenAIUsageFormat implements CanMapUsage
{
    public function fromData(array $data): Usage {
        return new Usage(
            inputTokens: $data['usage']['prompt_tokens'] ?? 0,
            outputTokens: $data['usage']['completion_tokens'] ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: $data['usage']['prompt_tokens_details']['cached_tokens'] ?? 0,
            reasoningTokens: $data['usage']['prompt_tokens_details']['reasoning_tokens'] ?? 0,
        );
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Drivers/OpenAI/OpenAIBodyFormat.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class OpenAIBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    public function map(
        array        $messages = [],
        string       $model = '',
        array        $tools = [],
        string|array $toolChoice = '',
        array        $responseFormat = [],
        array        $options = [],
        OutputMode   $mode = OutputMode::Unrestricted,
    ) : array {
        $options = array_merge($this->config->options, $options);

        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ]), $options);

        if ($options['stream'] ?? false) {
            $request['stream_options']['include_usage'] = true;
        }

        $request = $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);

        return $request;
    }

    // INTERNAL ///////////////////////////////////////////////

    protected function applyMode(
        array        $request,
        OutputMode   $mode,
        array        $tools,
        string|array $toolChoice,
        array        $responseFormat
    ) : array {
        $request['response_format'] = $responseFormat ?: $request['response_format'] ?? [];

        switch($mode) {
            case OutputMode::Json:
                $request['response_format'] = [
                    'type' => 'json_object'
                ];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $request['response_format'] = ['type' => 'text'];
                break;
            case OutputMode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $responseFormat['json_schema']['name'] ?? $responseFormat['name'] ?? 'schema',
                        'schema' => $responseFormat['json_schema']['schema'] ?? $responseFormat['schema'] ?? [],
                        'strict' => $responseFormat['json_schema']['strict'] ?? $responseFormat['strict'] ?? true,
                    ],
                ];
                break;
            case OutputMode::Unrestricted:
                if (isset($request['response_format']['type']) && $request['response_format']['type'] === 'json_object') {
                    unset($request['response_format']['schema']);
                }
                break;
        }

        $request['tools'] = $tools ?? [];
        $request['tool_choice'] = $toolChoice ?? [];
        return array_filter($request, fn($value) => $value !== null && $value !== [] && $value !== '');
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Drivers/OpenAI/OpenAIResponseAdapter.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

use Cognesy\Polyglot\LLM\Contracts\CanMapUsage;
use Cognesy\Polyglot\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\Data\ToolCall;
use Cognesy\Polyglot\LLM\Data\ToolCalls;

class OpenAIResponseAdapter implements ProviderResponseAdapter
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    public function fromResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }

    public function fromStreamResponse(array $data): ?PartialLLMResponse {
        if ($data === null || empty($data)) {
            return null;
        }
        return new PartialLLMResponse(
            contentDelta: $this->makeContentDelta($data),
            toolId: $this->makeToolId($data),
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }

    public function fromStreamData(string $data): string|bool {
        if (!str_starts_with($data, 'data:')) {
            return '';
        }
        $data = trim(substr($data, 5));
        return match(true) {
            $data === '[DONE]' => false,
            default => $data,
        };
    }

    protected function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromArray(array_map(
            callback: fn(array $call) => $this->makeToolCall($call),
            array: $data['choices'][0]['message']['tool_calls'] ?? []
        ));
    }

    protected function makeToolCall(array $data) : ?ToolCall {
        if (empty($data)) {
            return null;
        }
        if (!isset($data['function'])) {
            return null;
        }
        if (!isset($data['id'])) {
            return null;
        }
        return ToolCall::fromArray($data['function'])?->withId($data['id']);
    }

    protected function makeContent(array $data): string {
        $contentMsg = $data['choices'][0]['message']['content'] ?? '';
        $contentFnArgs = $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            !empty($contentMsg) => $contentMsg,
            !empty($contentFnArgs) => $contentFnArgs,
            default => ''
        };
    }

    protected function makeContentDelta(array $data): string {
        $deltaContent = $data['choices'][0]['delta']['content'] ?? '';
        $deltaFnArgs = $data['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            ('' !== $deltaContent) => $deltaContent,
            ('' !== $deltaFnArgs) => $deltaFnArgs,
            default => ''
        };
    }

    protected function makeToolId(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['id'] ?? '';
    }

    protected function makeToolNameDelta(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '';
    }

    protected function makeToolArgsDelta(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/InferenceStream.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM;

use Closure;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\Events\LLMResponseReceived;
use Cognesy\Polyglot\LLM\Events\PartialLLMResponseReceived;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Json\Json;
use Generator;

/**
 * The InferenceStream class is responsible for handling and processing streamed responses
 * from language models in a structured and event-driven manner. It allows for real-time
 * processing of incoming data and supports partial and cumulative responses.
 */
class InferenceStream
{
    protected EventDispatcher $events;
    protected EventStreamReader $reader;
    protected Generator $stream;
    protected HttpClientResponse $response;
    protected CanHandleInference $driver;
    protected bool $streamReceived = false;
    protected array $streamEvents = [];
    protected LLMConfig $config;

    protected array $llmResponses = [];
    protected ?LLMResponse $finalLLMResponse = null;
    protected ?PartialLLMResponse $lastPartialLLMResponse = null;
    protected ?Closure $onPartialResponse = null;

    public function __construct(
        HttpClientResponse $response,
        CanHandleInference $driver,
        LLMConfig          $config,
        ?EventDispatcher   $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->driver = $driver;
        $this->config = $config;
        $this->response = $response;

        $this->stream = $this->response->stream();
        $this->reader = new EventStreamReader(
            parser: $this->driver->fromStreamData(...),
            events: $this->events,
        );
    }

    /**
     * Generates and yields partial LLM responses from the given stream.
     *
     * @return Generator<PartialLLMResponse> A generator yielding partial LLM responses.
     */
    public function responses() : Generator {
        foreach ($this->makePartialLLMResponses($this->stream) as $partialLLMResponse) {
            yield $partialLLMResponse;
        }
    }

    /**
     * Retrieves all partial LLM responses from the given stream.
     *
     * @return PartialLLMResponse[] An array of all partial LLM responses.
     */
    public function all() : array {
        return $this->getAllPartialLLMResponses($this->stream);
    }

    /**
     * Returns the last partial response for the stream.
     * It will contain accumulated content and finish reason.
     *
     * @return ?LLMResponse
     */
    public function final() : ?LLMResponse {
        return $this->getFinalResponse($this->stream);
    }

    /**
     * Sets a callback to be called when a partial response is received.
     *
     * @param callable $callback
     */
    public function onPartialResponse(callable $callback) : self {
        $this->onPartialResponse = $callback(...);
        return $this;
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * Retrieves the final LLM response from the given stream of partial responses.
     *
     * @param Generator<PartialLLMResponse> $stream A generator yielding raw partial LLM response strings.
     * @return LLMResponse|null The final LLMResponse object or null if not available.
     */
    protected function getFinalResponse(Generator $stream) : ?LLMResponse {
        if ($this->finalLLMResponse === null) {
            foreach ($this->makePartialLLMResponses($stream) as $partialResponse) { $tmp = $partialResponse; }
        }
        return $this->finalLLMResponse;
    }

    /**
     * Retrieves all partial LLM responses from the provided generator stream.
     *
     * @param Generator<string> $stream The generator stream producing raw partial LLM response strings.
     * @return PartialLLMResponse[] An array containing all PartialLLMResponse objects.
     */
    protected function getAllPartialLLMResponses(Generator $stream) : array {
        if ($this->finalLLMResponse === null) {
            foreach ($this->makePartialLLMResponses($stream) as $partialResponse) { $tmp = $partialResponse; }
        }
        return $this->llmResponses;
    }

    /**
     * Processes the given stream to generate partial LLM responses and enriches them with accumulated content and finish reason.
     *
     * @param Generator<string> $stream The stream to be processed to extract and enrich partial LLM responses.
     * @return Generator<PartialLLMResponse> A generator yielding enriched PartialLLMResponse objects.
     */
    private function makePartialLLMResponses(Generator $stream) : Generator {
        $content = '';
        $reasoningContent = '';
        $finishReason = '';
        $this->llmResponses = [];
        $this->lastPartialLLMResponse = null;

        foreach ($this->getEventStream($stream) as $streamEvent) {
            if ($streamEvent === null || $streamEvent === '') {
                continue;
            }
            $data = Json::decode($streamEvent, []);
            $partialResponse = $this->driver->fromStreamResponse($data);
            if ($partialResponse === null) {
                continue;
            }
            $this->llmResponses[] = $partialResponse;

            // add accumulated content and last finish reason
            if ($partialResponse->finishReason !== '') {
                $finishReason = $partialResponse->finishReason;
            }
            $content .= $partialResponse->contentDelta;
            $reasoningContent .= $partialResponse->reasoningContentDelta;
            $enrichedResponse = $partialResponse
                ->withContent($content)
                ->withReasoningContent($reasoningContent)
                ->withFinishReason($finishReason);
            $this->events->dispatch(new PartialLLMResponseReceived($enrichedResponse));

            $this->lastPartialLLMResponse = $enrichedResponse;
            if ($this->onPartialResponse !== null) {
                ($this->onPartialResponse)($enrichedResponse);
            }
            yield $enrichedResponse;
        }
        $this->finalLLMResponse = LLMResponse::fromPartialResponses($this->llmResponses);
        $this->events->dispatch(new LLMResponseReceived($this->finalLLMResponse));
    }

    /**
     * Processes and retrieves events from the provided stream generator.
     *
     * @param Generator<string|null> $stream The input generator stream containing events.
     *
     * @return Generator<string> A generator yielding individual events from the processed stream.
     */
    private function getEventStream(Generator $stream) : Generator {
        if (!$this->streamReceived) {
            foreach($this->streamFromResponse($stream) as $event) {
                $this->streamEvents[] = $event;
                yield $event;
            }
            $this->streamReceived = true;
            return;
        }
        reset($this->streamEvents);
        yield from $this->streamEvents;
    }

    /**
     * Processes a stream of data and returns a generator of parsed events.
     *
     * @param Generator<string> $stream The input data stream to be processed.
     * @return Generator<string|null> A generator yielding parsed events from the input stream.
     */
    private function streamFromResponse(Generator $stream) : Generator {
        return $this->reader->eventsFrom($stream);
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Contracts/CanMapMessages.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Contracts;

interface CanMapMessages
{
    public function map(array $messages): array;
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Contracts/CanHandleInference.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\InferenceRequest;

interface CanHandleInference
{
    public function handle(InferenceRequest $request) : HttpClientResponse;
    public function fromResponse(array $data): ?LLMResponse;
    public function fromStreamResponse(array $data) : ?PartialLLMResponse;
    public function fromStreamData(string $data): string|bool;
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Contracts/CanMapUsage.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Polyglot\LLM\Data\Usage;

interface CanMapUsage
{
    public function fromData(array $data): Usage;
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Contracts/CanMapRequestBody.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Polyglot\LLM\Enums\OutputMode;

interface CanMapRequestBody
{
    public function map(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        OutputMode $mode
    ): array;
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Contracts/ProviderRequestAdapter.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

interface ProviderRequestAdapter
{
    public function toHttpClientRequest(
        array        $messages,
        string       $model,
        array        $tools,
        string|array $toolChoice,
        array        $responseFormat,
        array        $options,
        OutputMode   $mode,
    ) : HttpClientRequest;
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Contracts/ProviderResponseAdapter.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;

interface ProviderResponseAdapter
{
    public function fromResponse(array $data): ?LLMResponse;
    public function fromStreamResponse(array $data): ?PartialLLMResponse;
    public function fromStreamData(string $data): string|bool;
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Enums/LLMProviderType.php`:

```php
<?php
namespace Cognesy\Polyglot\LLM\Enums;

enum LLMProviderType : string
{
    case A21 = 'a21';
    case Anthropic = 'anthropic';
    //case Anyscale = 'anyscale';
    case Azure = 'azure';
    case Cerebras = 'cerebras';
    case CohereV1 = 'cohere1';
    case CohereV2 = 'cohere2';
    case DeepSeek = 'deepseek';
    case Fireworks = 'fireworks';
    case Gemini = 'gemini';
    case GeminiOAI = 'gemini-oai';
    case Groq = 'groq';
    case Jina = 'jina';
    case Minimaxi = 'minimaxi';
    case Mistral = 'mistral';
    case Moonshot = 'moonshot';
    case Ollama = 'ollama';
    case OpenAI = 'openai';
    case OpenRouter = 'openrouter';
    case Perplexity = 'perplexity';
    case SambaNova = 'sambanova';
    case Together = 'together';
    case XAi = 'xai';
    case OpenAICompatible = 'openai-compatible';
    case Other = 'other';
    case Unknown = 'unknown';

    public function is(LLMProviderType $clientType) : bool {
        return $this->value === $clientType->value;
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Enums/LLMFinishReason.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Enums;

enum LLMFinishReason : string
{
    case Stop = 'stop';
    case Length = 'length';
    case ToolCalls = 'tool_calls';
    case ContentFilter = 'content_filter';
    case Error = 'error';
    case Other = 'other';

    public function equals(string|LLMFinishReason $reason) : bool {
        return match(true) {
            $reason instanceof LLMFinishReason => ($this->value === $reason->value),
            is_string($reason) => ($this->value === $reason),
            default => false,
        };
    }

    public static function fromText(string $text) : LLMFinishReason {
        $text = strtolower($text);
        return match ($text) {
            'blocklist' => self::ContentFilter,
            'complete' => self::Stop,
            'error' => self::Error,
            'finish_reason_unspecified' => self::Other,
            'language' => self::ContentFilter,
            'length' => self::Length,
            'malformed_function_call' => self::Error,
            'max_tokens' => self::Length,
            'model_length' => self::Length,
            'other' => self::Other,
            'prohibited_content' => self::ContentFilter,
            'recitation' => self::ContentFilter,
            'safety' => self::ContentFilter,
            'spii' => self::ContentFilter,
            'stop' => self::Stop,
            'stop_sequence' => self::Stop,
            'tool_call' => self::ToolCalls,
            'tool_calls' => self::ToolCalls,
            'tool_use' => self::ToolCalls,
            default => self::Other,
        };
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Enums/OutputMode.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Enums;

/**
 * OutputMode is an enumeration representing different modes of responses or processing types.
 * Each case corresponds to a specific mode with an associated string value.
 *
 * Enum Cases:
 * - Tools: Represents a "tool_call" mode.
 * - Json: Represents a "json" mode.
 * - JsonSchema: Represents a "json_schema" mode.
 * - MdJson: Represents a "md_json" mode.
 * - Text: Represents an unstructured "text" response mode.
 * - Unrestricted: Unrestricted mode - no specific format or restrictions, follows default LLM response.
 */
enum OutputMode : string
{
    case Tools = 'tool_call';
    case Json = 'json';
    case JsonSchema = 'json_schema';
    case MdJson = 'md_json';
    case Text = 'text'; // unstructured text response
    case Unrestricted = 'unrestricted';

    /**
     * Checks whether the given mode matches the current mode.
     *
     * @param array|OutputMode $mode The mode to compare, can be an array of modes or a single Mode instance.
     * @return bool Returns true if the given mode matches the current mode, false otherwise.
     */
    public function is(array|OutputMode $mode) : bool {
        return match(true) {
            is_array($mode) => $this->isIn($mode),
            default => $this->value === $mode->value,
        };
    }

    /**
     * Determines whether the current instance is present in the given array of modes.
     *
     * @param array $modes An array of modes to check against.
     * @return bool Returns true if the current instance is found in the array of modes, false otherwise.
     */
    public function isIn(array $modes) : bool {
        return in_array($this, $modes);
    }

    public static function fromText(string $mode) : OutputMode {
        return match($mode) {
            'tool_call' => self::Tools,
            'json' => self::Json,
            'json_schema' => self::JsonSchema,
            'md_json' => self::MdJson,
            'text' => self::Text,
            default => self::Unrestricted,
        };
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Enums/LLMContentType.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Enums;

enum LLMContentType : string
{
    case Text = 'text';
    case Thinking = 'thinking';
    case Citation = 'citation';
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/InferenceRequest.php`:

```php
<?php
namespace Cognesy\Polyglot\LLM;

use Cognesy\Polyglot\LLM\Data\CachedContext;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

/**
 * Represents a request for an inference operation, holding configuration parameters
 * such as messages, model, tools, tool choices, response format, options, mode,
 * and a cached context if applicable.
 */
class InferenceRequest
{
    protected array $messages = [];
    protected string $model = '';
    protected array $tools = [];
    protected string|array $toolChoice = [];
    protected array $responseFormat = [];
    protected array $options = [];
    protected ?OutputMode $mode = null;
    protected ?CachedContext $cachedContext = null;

    public function __construct(
        string|array   $messages = [],
        string         $model = '',
        array          $tools = [],
        string|array   $toolChoice = [],
        array          $responseFormat = [],
        array          $options = [],
        ?OutputMode    $mode = null,
        ?CachedContext $cachedContext = null,
    ) {
        $this->cachedContext = $cachedContext;

        $this->model = $model;
        $this->options = $options;
        $this->mode = $mode;

        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
        $this->responseFormat = $responseFormat;

        $this->withMessages($messages);
    }

    /**
     * Retrieves the array of messages.
     *
     * @return array Returns the array containing messages.
     */
    public function messages() : array {
        return $this->messages;
    }

    /**
     * Sets the messages for the current instance.
     *
     * @param string|array $messages The message content to set. Can be either a string, which is converted
     * into an array with a predefined structure, or an array provided directly.
     * @return self Returns the current instance with the updated messages.
     */
    public function withMessages(string|array $messages) : self {
        $this->messages = match(true) {
            is_string($messages) => [['role' => 'user', 'content' => $messages]],
            default => $messages,
        };
        return $this;
    }

    /**
     * Retrieves the model.
     *
     * @return string The model of the object.
     */
    public function model() : string {
        return $this->model;
    }

    /**
     * Sets the model to be used and returns the current instance.
     *
     * @param string $model The name of the model to set.
     * @return self The current instance with the updated model.
     */
    public function withModel(string $model) : self {
        $this->model = $model;
        return $this;
    }

    /**
     * Determines whether the content or resource is being streamed.
     *
     * @return bool True if streaming is enabled, false otherwise.
     */
    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    /**
     * Sets the streaming option for the current instance.
     *
     * @param bool $streaming Whether to enable streaming.
     * @return self The current instance with the updated streaming option.
     */
    public function withStreaming(bool $streaming) : self {
        $this->options['stream'] = $streaming;
        return $this;
    }

    /**
     * Retrieves the list of tools based on the current mode.
     *
     * @return array An array of tools if the mode is set to Tools, otherwise an empty array.
     */
    public function tools() : array {
        return match($this->mode) {
            OutputMode::Tools => $this->tools,
            default => [],
        };
    }

    /**
     * Sets the tools to be used and returns the current instance.
     *
     * @param array $tools An array of tools to be assigned.
     * @return self The current instance with updated tools.
     */
    public function withTools(array $tools) : self {
        $this->tools = $tools;
        return $this;
    }

    /**
     * Retrieves the tool choice based on the current mode.
     *
     * @return string|array The tool choice if the mode is set to 'Tools', otherwise an empty array.
     */
    public function toolChoice() : string|array {
        return match($this->mode) {
            OutputMode::Tools => $this->toolChoice,
            default => [],
        };
    }

    /**
     * Sets the tool choice for the current instance.
     *
     * @param string|array $toolChoice The tool choice to be set, which can be a string or an array.
     * @return self Returns the current instance with the updated tool choice.
     */
    public function withToolChoice(string|array $toolChoice) : self {
        $this->toolChoice = $toolChoice;
        return $this;
    }

    /**
     * Retrieves the response format configuration based on the current mode.
     *
     * @return array An array representing the response format, varying depending on the mode.
     *               Includes schema details for JSON or JSON schema modes, or defaults to the
     *               existing response format configuration for other modes.
     */
    public function responseFormat() : array {
        return $this->responseFormat;
    }

    /**
     * Sets the response format configuration.
     *
     * @param array $responseFormat An associative array defining the response format settings.
     * @return self The current instance with the updated response format.
     */
    public function withResponseFormat(array $responseFormat) : self {
        $this->responseFormat = $responseFormat;
        return $this;
    }

    /**
     * Retrieves the array of options configured for the current instance.
     *
     * @return array The array of options.
     */
    public function options() : array {
        return $this->options;
    }

    /**
     * Sets the options for the current instance and returns it.
     *
     * @param array $options An associative array of options to configure the instance.
     * @return self The current instance with updated options.
     */
    public function withOptions(array $options) : self {
        $this->options = $options;
        return $this;
    }

    /**
     * Retrieves the current mode of the object.
     *
     * @return OutputMode The current mode instance.
     */
    public function outputMode() : ?OutputMode {
        return $this->mode;
    }

    /**
     * Sets the mode for the current instance and returns the updated instance.
     *
     * @param OutputMode $mode The mode to be set.
     * @return self The current instance with the updated mode.
     */
    public function withOutputMode(OutputMode $mode) : self {
        $this->mode = $mode;
        return $this;
    }

    /**
     * Retrieves the cached context if available.
     *
     * @return CachedContext|null The cached context instance or null if not set.
     */
    public function cachedContext() : ?CachedContext {
        return $this->cachedContext;
    }

    /**
     * Sets the cached context for the current instance.
     *
     * @param CachedContext|null $cachedContext The cached context to be set, or null to clear it.
     * @return self The current instance for method chaining.
     */
    public function withCachedContext(?CachedContext $cachedContext) : self {
        $this->cachedContext = $cachedContext;
        return $this;
    }

    /**
     * Converts the current object state into an associative array.
     *
     * @return array An associative array containing the object's properties and their values.
     */
    public function toArray() : array {
        return [
            'messages' => $this->messages,
            'model' => $this->model,
            'tools' => $this->tools,
            'tool_choice' => $this->toolChoice,
            'response_format' => $this->responseFormat,
            'options' => $this->options,
            'mode' => $this->mode?->value,
        ];
    }

    /**
     * Returns a cloned instance of the current object with cached context applied if available.
     * If no cached context is set, it returns the current instance unchanged.
     *
     * @return self A new instance with the cached context applied, or the current instance if no cache is set.
     */
    public function withCacheApplied() : self {
        if (!isset($this->cachedContext)) {
            return $this;
        }

        $cloned = clone $this;
        $cloned->messages = array_merge($this->cachedContext->messages, $this->messages);
        $cloned->tools = empty($this->tools) ? $this->cachedContext->tools : $this->tools;
        $cloned->toolChoice = empty($this->toolChoice) ? $this->cachedContext->toolChoice : $this->toolChoice;
        $cloned->responseFormat = empty($this->responseFormat) ? $this->cachedContext->responseFormat : $this->responseFormat;
        return $cloned;
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Events/LLMResponseReceived.php`:

```php
<?php
namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

class LLMResponseReceived extends Event
{
    public function __construct(
        public LLMResponse $llmResponse,
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->llmResponse);
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Events/StreamDataParsed.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Events;

class StreamDataParsed extends \Cognesy\Utils\Events\Event
{
    public function __construct(
        public string $content,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->content;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Events/PartialLLMResponseReceived.php`:

```php
<?php
namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

class PartialLLMResponseReceived extends Event
{
    public function __construct(
        public PartialLLMResponse $partialLLMResponse
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->partialLLMResponse);
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Events/StreamDataReceived.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Events;

class StreamDataReceived extends \Cognesy\Utils\Events\Event
{
    public function __construct(
        public string $content,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->content;
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Events/InferenceRequested.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

class InferenceRequested extends Event
{
    public function __construct(
        public InferenceRequest $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->request);
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Inference.php`:

```php
<?php
namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\CachedContext;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Events\Traits\HandlesEvents;

/**
 * Class Inference
 *
 * Handles LLM inference operations including configuration management, HTTP client handling, and event dispatching.
 */
class Inference
{
    use HandlesEvents;
    use Traits\HandlesFluentMethods;

    protected LLM $llm;
    protected CachedContext $cachedContext;
    protected InferenceRequest $request;

    /**
     * Constructor for initializing dependencies and configurations.
     *
     * @param LLM|null $llm LLM object.
     * @param EventDispatcher|null $events Event dispatcher.
     *
     * @return void
     */
    public function __construct(
        ?LLM                $llm = null,
        ?EventDispatcher    $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->llm = $llm ?? new LLM(events: $this->events);
        $this->request = new InferenceRequest();
    }

    // STATIC //////////////////////////////////////////////////////////////////

    /**
     * Generates a text response based on the provided messages and configuration.
     *
     * @param string|array $messages The input messages to process.
     * @param string $connection The connection string.
     * @param string $model The model identifier.
     * @param array $options Additional options for the inference.
     *
     * @return string The generated text response.
     */
    public static function text(
        string|array $messages,
        string       $connection = '',
        string       $model = '',
        array        $options = []
    ): string {
        return (new Inference)
            ->withConnection($connection)
            ->create(
                messages: $messages,
                model: $model,
                options: $options,
                mode: OutputMode::Text,
            )
            ->toText();
    }

    public static function fromDsn(string $dsn): self {
        return (new self)->withConfig(LLMConfig::fromDSN($dsn));
    }

    // PUBLIC //////////////////////////////////////////////////////////////////

    /**
     * Sets the LLM instance to be used.
     *
     * @param LLM $llm The LLM instance to set.
     * @return self Returns the current instance.
     */
    public function withLLM(LLM $llm): self {
        $this->llm = $llm;
        return $this;
    }

    /**
     * Updates the configuration and re-initializes the driver.
     *
     * @param LLMConfig $config The configuration object to set.
     *
     * @return self
     */
    public function withConfig(LLMConfig $config): self {
        $this->llm->withConfig($config);
        return $this;
    }

    /**
     * Sets the connection and updates the configuration and driver.
     *
     * @param string $connection The connection string to be used.
     *
     * @return self Returns the current instance with the updated connection.
     */
    public function withConnection(string $connection): self {
        if (empty($connection)) {
            return $this;
        }
        $this->llm->withConnection($connection);
        return $this;
    }

    /**
     * Sets a custom HTTP client and updates the inference driver accordingly.
     *
     * @param CanHandleHttpRequest $httpClient The custom HTTP client handler.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function withHttpClient(CanHandleHttpRequest $httpClient): self {
        $this->llm->withHttpClient($httpClient);
        return $this;
    }

    /**
     * Sets the driver for inference handling and returns the current instance.
     *
     * @param CanHandleInference $driver The inference handler to be set.
     *
     * @return self
     */
    public function withDriver(CanHandleInference $driver): self {
        $this->llm->withDriver($driver);
        return $this;
    }

    /**
     * Enable or disable debugging for the current instance.
     *
     * @param bool $debug Whether to enable debug mode. Default is true.
     *
     * @return self
     */
    public function withDebug(bool $debug = true): self {
        $this->llm->withDebug($debug);
        return $this;
    }

    /**
     * Sets a cached context with provided messages, tools, tool choices, and response format.
     *
     * @param string|array $messages Messages to be cached in the context.
     * @param array $tools Tools to be included in the cached context.
     * @param string|array $toolChoice Tool choices for the cached context.
     * @param array $responseFormat Format for responses in the cached context.
     *
     * @return self
     */
    public function withCachedContext(
        string|array $messages = [],
        array        $tools = [],
        string|array $toolChoice = [],
        array        $responseFormat = [],
    ): self {
        $this->cachedContext = new CachedContext($messages, $tools, $toolChoice, $responseFormat);
        return $this;
    }

    /**
     * Creates an inference request and returns the inference response.
     *
     * @param InferenceRequest $request The inference request object.
     *
     * @return InferenceResponse The response from the inference request.
     */
    public function withRequest(InferenceRequest $request): InferenceResponse {
        return new InferenceResponse(
            response: $this->llm->handleInferenceRequest($request),
            driver: $this->llm->driver(),
            config: $this->llm->config(),
            isStreamed: $request->isStreamed(),
            events: $this->events,
        );
    }

    /**
     * Creates an inference request and returns the inference response.
     *
     * @param string|array $messages The input messages for the inference.
     * @param string $model The model to be used for the inference.
     * @param array $tools The tools to be used for the inference.
     * @param string|array $toolChoice The choice of tools for the inference.
     * @param array $responseFormat The format of the response.
     * @param array $options Additional options for the inference.
     * @param OutputMode $mode The mode of operation for the inference.
     *
     * @return InferenceResponse The response from the inference request.
     */
    public function create(
        string|array $messages = [],
        string       $model = '',
        array        $tools = [],
        string|array $toolChoice = [],
        array        $responseFormat = [],
        array        $options = [],
        ?OutputMode   $mode = null,
    ): InferenceResponse {
        return $this->withRequest(new InferenceRequest(
            messages: $messages ?: $this->request->messages(),
            model: $model ?: $this->request->model() ?: $this->config()->model,
            tools: $tools ?: $this->request->tools(),
            toolChoice: $toolChoice ?: $this->request->toolChoice(),
            responseFormat: $responseFormat ?: $this->request->responseFormat(),
            options: array_merge($this->request->options(), $options),
            mode: $mode ?: $this->request->outputMode() ?: OutputMode::Unrestricted,
            cachedContext: $this->cachedContext ?? null
        ));
    }

    /**
     * Retrieves the LLM instance.
     *
     * @return LLM The LLM instance.
     */
    public function llm() : LLM {
        return $this->llm;
    }

    /**
     * Retrieves the LLM configuration instance.
     *
     * @return LLMConfig The LLM configuration instance.
     */
    public function config() : LLMConfig {
        return $this->llm->config();
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/ModularDriverFactory.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicMessageFormat;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicRequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Polyglot\LLM\Drivers\Azure\AzureOpenAIRequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\Cerebras\CerebrasBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1BodyFormat;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1MessageFormat;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1RequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1ResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1UsageFormat;
use Cognesy\Polyglot\LLM\Drivers\CohereV2\CohereV2BodyFormat;
use Cognesy\Polyglot\LLM\Drivers\CohereV2\CohereV2RequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\CohereV2\CohereV2ResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\CohereV2\CohereV2UsageFormat;
use Cognesy\Polyglot\LLM\Drivers\Deepseek\DeepseekResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\Fireworks\FireworksBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiMessageFormat;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiRequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiUsageFormat;
use Cognesy\Polyglot\LLM\Drivers\GeminiOAI\GeminiOAIBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\GeminiOAI\GeminiOAIUsageFormat;
use Cognesy\Polyglot\LLM\Drivers\Groq\GroqUsageFormat;
use Cognesy\Polyglot\LLM\Drivers\Minimaxi\MinimaxiBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\Mistral\MistralBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\ModularLLMDriver;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\Perplexity\PerplexityBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\SambaNova\SambaNovaBodyFormat;
use Cognesy\Polyglot\LLM\Drivers\XAI\XAiMessageFormat;
use Cognesy\Utils\Events\EventDispatcher;
use InvalidArgumentException;

/**
 * Factory class for creating inference driver instances based
 * on the specified configuration and provider type.
 */
class ModularDriverFactory
{
    private static array $drivers = [];

    public static function registerDriver(string $name, string|callable $driver): void
    {
        self::$drivers[$name] = match (true) {
            is_callable($driver) => $driver,
            is_string($driver) => fn($config, $httpClient, $events) => new $driver(
                $config,
                $httpClient,
                $events
            ),
        };
    }

    /**
     * Creates and returns an appropriate driver instance based on the given configuration.
     *
     * @param LLMConfig $config Configuration object specifying the provider type and other necessary settings.
     * @param CanHandleHttpRequest $httpClient An HTTP client instance to handle HTTP requests.
     *
     * @return CanHandleInference A driver instance matching the specified provider type.
     * @throws InvalidArgumentException If the provider type is not supported.
     */
    public function makeDriver(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference
    {
        $type = $config->providerType;
        $driver = self::$drivers[$type] ?? $this->getBundledDriver($type);
        if ($driver === null) {
            throw new InvalidArgumentException("Provider type not supported - missing built-in or custom driver: {$type}");
        }
        return $driver($config, $httpClient, $events);
    }

    public function anthropic(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new AnthropicRequestAdapter(
                $config,
                new AnthropicBodyFormat($config, new AnthropicMessageFormat())
            ),
            new AnthropicResponseAdapter(new AnthropicUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function azure(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new AzureOpenAIRequestAdapter(
                $config,
                new OpenAIBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function cerebras(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new CerebrasBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function cohereV1(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new CohereV1RequestAdapter(
                $config,
                new CohereV1BodyFormat($config, new CohereV1MessageFormat())
            ),
            new CohereV1ResponseAdapter(new CohereV1UsageFormat()),
            $httpClient,
            $events
        );
    }

    public function cohereV2(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new CohereV2RequestAdapter(
                $config,
                new CohereV2BodyFormat($config, new OpenAIMessageFormat())
            ),
            new CohereV2ResponseAdapter(new CohereV2UsageFormat()),
            $httpClient,
            $events
        );
    }

    public function deepseek(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new OpenAICompatibleBodyFormat($config, new OpenAIMessageFormat())
            ),
            new DeepseekResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function fireworks(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events) : CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new FireworksBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function gemini(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new GeminiRequestAdapter(
                $config,
                new GeminiBodyFormat($config, new GeminiMessageFormat())
            ),
            new GeminiResponseAdapter(new GeminiUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function geminiOAI(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new GeminiOAIBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new GeminiOAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function groq(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new OpenAICompatibleBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new GroqUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function mistral(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new MistralBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function minimaxi(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new MinimaxiBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function openAI(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new OpenAIBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function openAICompatible(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new OpenAICompatibleBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function perplexity(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new PerplexityBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function sambaNova(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new SambaNovaBodyFormat($config, new OpenAIMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    public function xAi(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        return new ModularLLMDriver(
            $config,
            new OpenAIRequestAdapter(
                $config,
                new OpenAICompatibleBodyFormat($config, new XAiMessageFormat())
            ),
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            $httpClient,
            $events
        );
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Returns factory to create LLM driver instance
     * @param string $name
     * @return callable|null
     */
    protected function getBundledDriver(string $name): ?callable
    {
        $drivers = [
            'anthropic' => fn($config, $httpClient, $events) => $this->anthropic($config, $httpClient, $events),
            'azure' => fn($config, $httpClient, $events) => $this->azure($config, $httpClient, $events),
            'cerebras' => fn($config, $httpClient, $events) => $this->cerebras($config, $httpClient, $events),
            'cohere1' => fn($config, $httpClient, $events) => $this->cohereV1($config, $httpClient, $events),
            'cohere2' => fn($config, $httpClient, $events) => $this->cohereV2($config, $httpClient, $events),
            'deepseek' => fn($config, $httpClient, $events) => $this->deepseek($config, $httpClient, $events),
            'fireworks' => fn($config, $httpClient, $events) => $this->fireworks($config, $httpClient, $events),
            'gemini' => fn($config, $httpClient, $events) => $this->gemini($config, $httpClient, $events),
            'gemini-oai' => fn($config, $httpClient, $events) => $this->geminiOAI($config, $httpClient, $events),
            'groq' => fn($config, $httpClient, $events) => $this->groq($config, $httpClient, $events),
            'minimaxi' => fn($config, $httpClient, $events) => $this->minimaxi($config, $httpClient, $events),
            'mistral' => fn($config, $httpClient, $events) => $this->mistral($config, $httpClient, $events),
            'openai' => fn($config, $httpClient, $events) => $this->openAI($config, $httpClient, $events),
            'perplexity' => fn($config, $httpClient, $events) => $this->perplexity($config, $httpClient, $events),
            'sambanova' => fn($config, $httpClient, $events) => $this->sambaNova($config, $httpClient, $events),
            'xai' => fn($config, $httpClient, $events) => $this->xAi($config, $httpClient, $events),
            // OpenAI compatible driver for generic OAI providers
            'a21' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
            'moonshot' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
            'ollama' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
            'openai-compatible' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
            'openrouter' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
            'together' => fn($config, $httpClient, $events) => $this->openAICompatible($config, $httpClient, $events),
        ];
        return $drivers[$name] ?? null;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/InferenceDriverFactory.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Drivers\A21\A21Driver;
use Cognesy\Polyglot\LLM\Drivers\Anthropic\AnthropicDriver;
use Cognesy\Polyglot\LLM\Drivers\Azure\AzureDriver;
use Cognesy\Polyglot\LLM\Drivers\Cerebras\CerebrasDriver;
use Cognesy\Polyglot\LLM\Drivers\CohereV1\CohereV1Driver;
use Cognesy\Polyglot\LLM\Drivers\CohereV2\CohereV2Driver;
use Cognesy\Polyglot\LLM\Drivers\Deepseek\DeepseekDriver;
use Cognesy\Polyglot\LLM\Drivers\Fireworks\FireworksDriver;
use Cognesy\Polyglot\LLM\Drivers\Gemini\GeminiDriver;
use Cognesy\Polyglot\LLM\Drivers\GeminiOAI\GeminiOAIDriver;
use Cognesy\Polyglot\LLM\Drivers\Groq\GroqDriver;
use Cognesy\Polyglot\LLM\Drivers\Meta\MetaDriver;
use Cognesy\Polyglot\LLM\Drivers\Minimaxi\MinimaxiDriver;
use Cognesy\Polyglot\LLM\Drivers\Mistral\MistralDriver;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleDriver;
use Cognesy\Polyglot\LLM\Drivers\Perplexity\PerplexityDriver;
use Cognesy\Polyglot\LLM\Drivers\SambaNova\SambaNovaDriver;
use Cognesy\Polyglot\LLM\Drivers\XAI\XAiDriver;
use Cognesy\Utils\Events\EventDispatcher;
use InvalidArgumentException;

/**
 * Factory class for creating inference driver instances based
 * on the specified configuration and provider type.
 */
class InferenceDriverFactory
{
    private static array $drivers = [];

    /**
     * Registers driver under given name
     *
     * @param string $name
     * @param string|callable $driver
     * @return void
     */
    public static function registerDriver(string $name, string|callable $driver) : void {
        self::$drivers[$name] = match(true) {
            is_callable($driver) => $driver,
            is_string($driver) => fn($config, $httpClient, $events) => new $driver(
                $config,
                $httpClient,
                $events
            ),
        };
    }

    /**
     * Creates and returns an appropriate driver instance based on the given configuration.
     *
     * @param LLMConfig $config Configuration object specifying the provider type and other necessary settings.
     * @param CanHandleHttpRequest $httpClient An HTTP client instance to handle HTTP requests.
     *
     * @return CanHandleInference A driver instance matching the specified provider type.
     * @throws InvalidArgumentException If the provider type is not supported.
     */
    public function makeDriver(LLMConfig $config, CanHandleHttpRequest $httpClient, EventDispatcher $events): CanHandleInference {
        $type = $config->providerType;
        $driver = self::$drivers[$type] ?? $this->getBundledDriver($type);
        if ($driver === null) {
            throw new InvalidArgumentException("Provider type not supported - missing built-in or custom driver: {$type}");
        }
        return $driver($config, $httpClient, $events);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Returns factory to create LLM driver instance
     * @param string $name
     * @return callable|null
     */
    protected function getBundledDriver(string $name) : ?callable {
        $drivers = [
            // Tailored drivers
            'a21' => fn($config, $httpClient, $events) => new A21Driver($config, $httpClient, $events),
            'anthropic' => fn($config, $httpClient, $events) => new AnthropicDriver($config, $httpClient, $events),
            'azure' => fn($config, $httpClient, $events) => new AzureDriver($config, $httpClient, $events),
            'cerebras' => fn($config, $httpClient, $events) => new CerebrasDriver($config, $httpClient, $events),
            'cohere1' => fn($config, $httpClient, $events) => new CohereV1Driver($config, $httpClient, $events),
            'cohere2' => fn($config, $httpClient, $events) => new CohereV2Driver($config, $httpClient, $events),
            'deepseek' => fn($config, $httpClient, $events) => new DeepseekDriver($config, $httpClient, $events),
            'fireworks' => fn($config, $httpClient, $events) => new FireworksDriver($config, $httpClient, $events),
            'gemini' => fn($config, $httpClient, $events) => new GeminiDriver($config, $httpClient, $events),
            'gemini-oai' => fn($config, $httpClient, $events) => new GeminiOAIDriver($config, $httpClient, $events),
            'groq' => fn($config, $httpClient, $events) => new GroqDriver($config, $httpClient, $events),
            'meta' => fn($config, $httpClient, $events) => new MetaDriver($config, $httpClient, $events),
            'minimaxi' => fn($config, $httpClient, $events) => new MinimaxiDriver($config, $httpClient, $events),
            'mistral' => fn($config, $httpClient, $events) => new MistralDriver($config, $httpClient, $events),
            'openai' => fn($config, $httpClient, $events) => new OpenAIDriver($config, $httpClient, $events),
            'perplexity' => fn($config, $httpClient, $events) => new PerplexityDriver($config, $httpClient, $events),
            'sambanova' => fn($config, $httpClient, $events) => new SambaNovaDriver($config, $httpClient, $events),
            'xai' => fn($config, $httpClient, $events) => new XAiDriver($config, $httpClient, $events),
            // OpenAI compatible driver for generic OAI providers
            'moonshot' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'ollama' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'openai-compatible' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'openrouter' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
            'together' => fn($config, $httpClient, $events) => new OpenAICompatibleDriver($config, $httpClient, $events),
       ];
        return $drivers[$name] ?? null;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Traits/HandlesFluentMethods.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Traits;

use Cognesy\Polyglot\LLM\Enums\OutputMode;

trait HandlesFluentMethods
{
    public function withMessages(string|array $messages): static
    {
        $this->request->withMessages($messages);
        return $this;
    }

    public function withModel(string $model): static
    {
        $this->request->withModel($model);
        return $this;
    }

    public function withTools(array $tools): static
    {
        $this->request->withTools($tools);
        return $this;
    }

    public function withToolChoice(string $toolChoice): static
    {
        $this->request->withToolChoice($toolChoice);
        return $this;
    }

    public function withResponseFormat(array $responseFormat): static
    {
        $this->request->withResponseFormat($responseFormat);
        return $this;
    }

    public function withOptions(array $options): static
    {
        $this->request->withOptions($options);
        return $this;
    }

    public function withStreaming(bool $stream = true): static
    {
        $options = $this->request->options();
        $options['stream'] = $stream;
        $this->request->withOptions($options);
        return $this;
    }


    public function withOutputMode(OutputMode $mode): static
    {
        $this->request->withOutputMode($mode);
        return $this;
    }
}


```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/EventStreamReader.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM;

use Closure;
use Cognesy\Polyglot\LLM\Events\StreamDataParsed;
use Cognesy\Polyglot\LLM\Events\StreamDataReceived;
use Cognesy\Utils\Events\EventDispatcher;
use Generator;

/**
 * Handles reading and processing event streams.
 *
 * The EventStreamReader is responsible for reading data from a stream,
 * processing each line of input, and dispatching events for raw and
 * parsed data. It provides a mechanism for custom parsing of stream
 * data and integrates with an event dispatching system.
 */
class EventStreamReader
{
    protected EventDispatcher $events;
    protected ?Closure $parser;

    public function __construct(
        ?Closure $parser = null,
        ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->parser = $parser;
    }

    /**
     * Processes data from a generator stream, dispatches events for received and parsed data,
     * and yields processed data.
     *
     * @param Generator $stream The input stream generator providing data to be processed.
     * @return Generator The generator yielding processed data after parsing.
     */
    public function eventsFrom(Generator $stream): Generator {
        foreach ($this->readLines($stream) as $line) {
            $this->events->dispatch(new StreamDataReceived($line));
            $processedData = $this->processLine($line);
            if ($processedData !== null) {
                $this->events->dispatch(new StreamDataParsed($processedData));
                yield $processedData;
            }
        }
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * Reads and extracts complete lines from a generator stream.
     *
     * @param Generator $stream The input stream generator providing chunks of data.
     * @return Generator A generator yielding complete lines of data ending with a newline character.
     */
    protected function readLines(Generator $stream): Generator {
        $buffer = '';
        foreach ($stream as $chunk) {
            $buffer .= $chunk;
            while (false !== ($pos = strpos($buffer, "\n"))) {
                yield substr($buffer, 0, $pos + 1);
                $buffer = substr($buffer, $pos + 1);
            }
        }
    }

    /**
     * Processes a single line of input, trims whitespace, attempts to parse it,
     * and optionally performs a debug dump if needed.
     *
     * @param string $line The input line to be processed.
     * @return string|null Returns the processed data as a string, or null if the line is empty or cannot be parsed.
     */
    protected function processLine(string $line): ?string {
        $line = trim($line);
        if ($line === '') {
            return null;
        }
        if (false === ($data = $this->parse($line))) {
            return null;
        }
        return $data;
    }

    /**
     * Parses a given input line using a custom parser if defined, or returns the line as is.
     *
     * @param string $line The input line to be parsed.
     * @return string|bool Returns the parsed line as a string if successful, or a boolean false on failure.
     */
    protected function parse(string $line): string|bool {
        return match(empty($this->parser)) {
            true => $line,
            false => ($this->parser)($line),
        };
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/LLM.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Events\InferenceRequested;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;

/**
 * This class represents an interface to Large Language Model provider APIs,
 * handling configurations, HTTP client integrations, inference drivers,
 * and event dispatching.
 */
class LLM
{
    protected LLMConfig $config;

    protected EventDispatcher $events;
    protected CanHandleHttpRequest $httpClient;
    protected CanHandleInference $driver;
    protected InferenceDriverFactory $driverFactory;

    /**
     * Constructor for initializing dependencies and configurations.
     *
     * @param string $connection The connection string.
     * @param LLMConfig|null $config Configuration object.
     * @param CanHandleHttpRequest|null $httpClient HTTP client handler.
     * @param CanHandleInference|null $driver Inference handler.
     * @param EventDispatcher|null $events Event dispatcher.
     *
     * @return void
     */
    public function __construct(
        string                $connection = '',
        ?LLMConfig            $config = null,
        ?CanHandleHttpRequest $httpClient = null,
        ?CanHandleInference   $driver = null,
        ?EventDispatcher      $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->config = $config ?? LLMConfig::load(
            connection: $connection ?: Settings::get('llm', "defaultConnection")
        );
        $this->httpClient = $httpClient ?? HttpClient::make(client: $this->config->httpClient, events: $this->events);

        $this->driverFactory = new InferenceDriverFactory();
        $this->driver = $driver ?? $this->driverFactory->makeDriver($this->config, $this->httpClient, $this->events);
    }

    // STATIC //////////////////////////////////////////////////////////////////

    /**
     * Creates a new LLM instance for the specified connection
     *
     * @param string $connection
     * @return self
     */
    public static function connection(string $connection = ''): self {
        return new self(connection: $connection);
    }

    public static function fromDSN(string $dsn): self {
        $config = LLMConfig::fromDSN($dsn);
        return new self(config: $config);
    }

    public static function registerDriver(string $name, string|callable $driver) : void {
        InferenceDriverFactory::registerDriver($name, $driver);
    }

    // PUBLIC //////////////////////////////////////////////////////////////////

    /**
     * Updates the configuration and re-initializes the driver.
     *
     * @param \Cognesy\Polyglot\LLM\Data\LLMConfig $config The configuration object to set.
     *
     * @return self
     */
    public function withConfig(LLMConfig $config): self {
        $this->config = $config;
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient, $this->events);
        return $this;
    }

    /**
     * Sets the connection and updates the configuration and driver.
     *
     * @param string $connection The connection string to be used.
     *
     * @return self Returns the current instance with the updated connection.
     */
    public function withConnection(string $connection): self {
        if (empty($connection)) {
            return $this;
        }
        $this->config = LLMConfig::load($connection);
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient, $this->events);
        return $this;
    }

    /**
     * Sets a custom HTTP client and updates the inference driver accordingly.
     *
     * @param CanHandleHttpRequest $httpClient The custom HTTP client handler.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function withHttpClient(CanHandleHttpRequest $httpClient): self {
        $this->httpClient = $httpClient;
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient, $this->events);
        return $this;
    }

    /**
     * Sets the driver for inference handling and returns the current instance.
     *
     * @param CanHandleInference $driver The inference handler to be set.
     *
     * @return self
     */
    public function withDriver(CanHandleInference $driver): self {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Enable or disable debugging for the current instance.
     *
     * @param bool $debug Whether to enable debug mode. Default is true.
     *
     * @return self
     */
    public function withDebug(bool $debug = true) : self {
        // TODO: needs to be solved - it only works when we're using HttpClient class as a driver
        $this->httpClient->withDebug($debug);
        return $this;
    }

    /**
     * Returns the current configuration object.
     *
     * @return LLMConfig
     */
    public function config() : LLMConfig {
        return $this->config;
    }

    /**
     * Returns the current inference driver.
     *
     * @return CanHandleInference
     */
    public function driver() : CanHandleInference {
        return $this->driver;
    }

    /**
     * Returns the HTTP response object for given inference request
     *
     * @param InferenceRequest $request
     * @return HttpClientResponse
     */
    public function handleInferenceRequest(InferenceRequest $request) : HttpClientResponse {
        $this->events->dispatch(new InferenceRequested($request));
        return $this->driver->handle($request);
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Data/LLMConfig.php`:

```php
<?php
namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Dsn\DSN;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

class LLMConfig
{
    public function __construct(
        public string $apiUrl = '',
        public string $apiKey = '',
        public string $endpoint = '',
        public array $queryParams = [],
        public array $metadata = [],
        public string $model = '',
        public int $maxTokens = 1024,
        public int $contextLength = 8000,
        public int $maxOutputLength = 4096,
        public string $httpClient = '',
        public string $providerType = 'openai-compatible',
        public array $options = [],
    ) {}

    public static function load(string $connection) : LLMConfig {
        if (!Settings::has('llm', "connections.$connection")) {
            throw new InvalidArgumentException("Unknown connection: $connection");
        }
        return new LLMConfig(
            apiUrl: Settings::get('llm', "connections.$connection.apiUrl"),
            apiKey: Settings::get('llm', "connections.$connection.apiKey", ''),
            endpoint: Settings::get('llm', "connections.$connection.endpoint"),
            queryParams: Settings::get('llm', "connections.$connection.queryParams", []),
            metadata: Settings::get('llm', "connections.$connection.metadata", []),
            model: Settings::get('llm', "connections.$connection.defaultModel", ''),
            maxTokens: Settings::get('llm', "connections.$connection.defaultMaxTokens", 1024),
            contextLength: Settings::get('llm', "connections.$connection.contextLength", 8000),
            maxOutputLength: Settings::get('llm', "connections.$connection.defaultMaxOutputLength", 4096),
            httpClient: Settings::get('llm', "connections.$connection.httpClient", ''),
            providerType: Settings::get('llm', "connections.$connection.providerType", 'openai-compatible'),
            options: Settings::get('llm', "connections.$connection.options", []),
        );
    }

    public static function fromArray(array $config) : LLMConfig {
        return new LLMConfig(
            apiUrl: $config['apiUrl'] ?? $config['api_url'] ?? '',
            apiKey: $config['apiKey'] ?? $config['api_key'] ?? '',
            endpoint: $config['endpoint'] ?? '',
            queryParams: $config['queryParams'] ?? $config['query_params'] ?? [],
            metadata: $config['metadata'] ?? [],
            model: $config['model'] ?? '',
            maxTokens: $config['maxTokens'] ?? $config['max_tokens'] ?? 1024,
            contextLength: $config['contextLength'] ?? $config['context_length'] ?? 8000,
            maxOutputLength: $config['maxOutputLength'] ?? $config['max_output_length'] ?? 4096,
            httpClient: $config['httpClient'] ?? $config['http_client'] ?? '',
            providerType: $config['providerType'] ?? $config['provider'] ?? 'openai-compatible',
            options: $config['options'] ?? [],
        );
    }

    public static function fromDSN(string $dsn) : LLMConfig {
        $data = DSN::fromString($dsn)->params();
        $connection = $data['connection'] ?? '';
        return match(true) {
            !empty($connection) => self::withOverrides(self::load($connection), $data),
            default => self::fromArray($data),
        };
    }

    private static function withOverrides(LLMConfig $config, array $overrides) : LLMConfig {
        $config->apiUrl = $overrides['apiUrl'] ?? $overrides['api_url'] ?? $config->apiUrl;
        $config->apiKey = $overrides['apiKey'] ?? $overrides['api_key'] ?? $config->apiKey;
        $config->endpoint = $overrides['endpoint'] ?? $config->endpoint;
        $config->queryParams = $overrides['queryParams'] ?? $overrides['query_params'] ?? $config->queryParams;
        $config->metadata = $overrides['metadata'] ?? $config->metadata;
        $config->model = $overrides['model'] ?? $config->model;
        $config->maxTokens = $overrides['maxTokens'] ?? $overrides['max_tokens'] ?? $config->maxTokens;
        $config->contextLength = $overrides['contextLength'] ?? $overrides['context_length'] ?? $config->contextLength;
        $config->maxOutputLength = $overrides['maxOutputLength'] ?? $overrides['max_output_length'] ?? $config->maxOutputLength;
        $config->httpClient = $overrides['httpClient'] ?? $overrides['http_client'] ?? $config->httpClient;
        $config->providerType = $overrides['providerType'] ?? $overrides['provider'] ?? $config->providerType;
        $config->options = $overrides['options'] ?? $config->options;
        return $config;
    }

    public function toArray() : array {
        return [
            'apiUrl' => $this->apiUrl,
            'apiKey' => $this->apiKey,
            'endpoint' => $this->endpoint,
            'queryParams' => $this->queryParams,
            'metadata' => $this->metadata,
            'model' => $this->model,
            'maxTokens' => $this->maxTokens,
            'contextLength' => $this->contextLength,
            'maxOutputLength' => $this->maxOutputLength,
            'httpClient' => $this->httpClient,
            'providerType' => $this->providerType,
            'options' => $this->options,
        ];
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Data/Usage.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Data;

class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheWriteTokens = 0,
        public int $cacheReadTokens = 0,
        public int $reasoningTokens = 0,
    ) {}

    public static function none() : Usage {
        return new Usage();
    }

    public static function fromArray(array $value) : static {
        return new Usage(
            $value['input'] ?? 0,
            $value['output'] ?? 0,
            $value['cacheWrite'] ?? 0,
            $value['cacheRead'] ?? 0,
            $value['reasoning'] ?? 0,
        );
    }

    public static function copy(Usage $usage) : static {
        return new Usage(
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheWriteTokens: $usage->cacheWriteTokens,
            cacheReadTokens: $usage->cacheReadTokens,
            reasoningTokens: $usage->reasoningTokens,
        );
    }

    public function total() : int {
        return $this->inputTokens
            + $this->outputTokens
            + $this->cacheWriteTokens
            + $this->cacheReadTokens
            + $this->reasoningTokens;
    }

    public function input() : int {
        return $this->inputTokens;
    }

    public function output() : int {
        return $this->outputTokens
            + $this->reasoningTokens;
    }

    public function cache() : int {
        return $this->cacheWriteTokens
            + $this->cacheReadTokens;
    }

    public function accumulate(Usage $usage) : void {
        $this->inputTokens += $usage->inputTokens;
        $this->outputTokens += $usage->outputTokens;
        $this->cacheWriteTokens += $usage->cacheWriteTokens;
        $this->cacheReadTokens += $usage->cacheReadTokens;
        $this->reasoningTokens += $usage->reasoningTokens;
    }

    public function toString() : string {
        return "Tokens: {$this->total()} (i:{$this->inputTokens} o:{$this->outputTokens} c:{$this->cache()} r:{$this->reasoningTokens})";
    }

    public function toArray() : array {
        return [
            'input' => $this->inputTokens,
            'output' => $this->outputTokens,
            'cacheWrite' => $this->cacheWriteTokens,
            'cacheRead' => $this->cacheReadTokens,
            'reasoning' => $this->reasoningTokens,
        ];
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Data/CachedContext.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Data;

class CachedContext
{
    public function __construct(
        public string|array $messages = [],
        public array $tools = [],
        public string|array $toolChoice = [],
        public array $responseFormat = [],
    ) {
        if (is_string($messages)) {
            $this->messages = ['role' => 'user', 'content' => $messages];
        }
    }

    public function messages() : array {
        return $this->messages;
    }

    public function tools() : array {
        return $this->tools;
    }

    public function toolChoice() : string|array {
        return $this->toolChoice;
    }

    public function responseFormat() : array {
        return $this->responseFormat;
    }

    public function merged(
        string|array $messages = [],
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
    ) {
        if (is_string($messages) && !empty($messages)) {
            $messages = ['role' => 'user', 'content' => $messages];
        }
        return new CachedContext(
            messages: array_merge($this->messages, $messages),
            tools: empty($tools) ? $this->tools : $tools,
            toolChoice: empty($toolChoice) ? $this->toolChoice : $toolChoice,
            responseFormat: empty($responseFormat) ? $this->responseFormat : $responseFormat,
        );
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Data/PartialLLMResponse.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Utils\Json\Json;

class PartialLLMResponse
{
    private mixed $value = null; // data extracted from response or tool calls
    private string $content = '';
    private string $reasoningContent = '';

    public function __construct(
        public string $contentDelta = '',
        public string $reasoningContentDelta = '',
        public string $toolId = '',
        public string $toolName = '',
        public string $toolArgs = '',
        public string $finishReason = '',
        public ?Usage $usage = null,
        public array  $responseData = [],
    ) {}

    // PUBLIC ////////////////////////////////////////////////

    public function hasValue() : bool {
        return $this->value !== null;
    }

    public function withValue(mixed $value) : self {
        $this->value = $value;
        return $this;
    }

    public function value() : mixed {
        return $this->value;
    }

    public function hasContent() : bool {
        return $this->content !== '';
    }

    public function withContent(string $content) : self {
        $this->content = $content;
        return $this;
    }

    public function content() : string {
        return $this->content;
    }

    public function reasoningContent() : string {
        return $this->reasoningContent;
    }

    public function withReasoningContent(string $reasoningContent) : self {
        $this->reasoningContent = $reasoningContent;
        return $this;
    }

    public function hasReasoningContent() : bool {
        return $this->reasoningContent !== '';
    }

    public function json(): string {
        if (!$this->hasContent()) {
            return '';
        }
        return Json::fromPartial($this->content)->toString();
    }

    public function withFinishReason(string $finishReason) : self {
        $this->finishReason = $finishReason;
        return $this;
    }

    public function usage() : Usage {
        return $this->usage ?? new Usage();
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Data/LLMResponse.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Polyglot\LLM\Enums\LLMFinishReason;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Json\Json;

/**
 * Represents a response from the LLM.
 */
class LLMResponse
{
    private mixed $value = null;

    public function __construct(
        private string     $content = '',
        private string     $finishReason = '',
        private ?ToolCalls $toolCalls = null,
        private string     $reasoningContent = '',
        private ?Usage     $usage = null,
        private array      $responseData = [],
    ) {
        $this->usage = $usage ?? new Usage();
    }

    // STATIC ////////////////////////////////////////////////

    /**
     * Create an LLMResponse from an array of PartialLLMResponses.
     *
     * @param PartialLLMResponse[] $partialResponses
     * @return LLMResponse
     */
    public static function fromPartialResponses(array $partialResponses) : LLMResponse {
        return (new self)->makeFromPartialResponses($partialResponses);
    }

    // PUBLIC ////////////////////////////////////////////////

    /**
     * Checks if the response has a processed / transformed value.
     *
     * @param mixed $value
     * @return LLMResponse
     */
    public function hasValue() : bool {
        return $this->value !== null;
    }

    /**
     * Set the processed / transformed value of the response.
     * @param mixed $value
     * @return $this
     */
    public function withValue(mixed $value) : self {
        $this->value = $value;
        return $this;
    }

    /**
     * Get the processed / transformed value of the response.
     * @return mixed
     */
    public function value() : mixed {
        return $this->value;
    }

    /**
     * Check if the response has content.
     * @return bool
     */
    public function hasContent() : bool {
        return $this->content !== '';
    }

    /**
     * Get the content of the response.
     * @return string
     */
    public function content() : string {
        return $this->content;
    }

    /**
     * Set the content of the response.
     * @param string $content
     * @return $this
     */
    public function withContent(string $content) : self {
        $this->content = $content;
        return $this;
    }

    /**
     * Set reasoning content of the response.
     * @return bool
     */
    public function withReasoningContent(string $reasoningContent) : self {
        $this->reasoningContent = $reasoningContent;
        return $this;
    }

    /**
     * Get the reasoning content of the response.
     * @return string
     */
    public function reasoningContent() : string {
        return $this->reasoningContent;
    }

    /**
     * Check if the response has reasoning content.
     * @return bool
     */
    public function hasReasoningContent() : bool {
        return $this->reasoningContent !== '';
    }

    /**
     * Find the JSON data in the response - try checking for tool calls (if any are present)
     * or find and extract JSON from the returned content.
     *
     * @return Json
     */
    public function findJsonData(OutputMode $mode = null): Json {
        return match(true) {
            ($mode == OutputMode::Tools) && $this->hasToolCalls() => match($this->toolCalls->hasSingle()) {
                true => Json::fromArray($this->toolCalls->first()?->args()),
                default => Json::fromArray($this->toolCalls->toArray()),
            },
            $this->hasContent() => Json::fromString($this->content),
            default => Json::fromString($this->content),
        };
    }

    public function hasToolCalls() : bool {
        return $this->toolCalls?->hasAny() ?? false;
    }

    public function usage() : Usage {
        return $this->usage ?? new Usage();
    }

    public function toolCalls() : ToolCalls {
        return $this->toolCalls ?? new ToolCalls();
    }

    public function finishReason() : LLMFinishReason {
        return LLMFinishReason::fromText($this->finishReason);
    }

    public function responseData() : array {
        return $this->responseData;
    }

    public function toArray() : array {
        return [
            'content' => $this->content,
            'reasoningContent' => $this->reasoningContent,
            'finishReason' => $this->finishReason,
            'toolCalls' => $this->toolCalls?->toArray() ?? [],
            'usage' => $this->usage->toArray(),
            'responseData' => $this->responseData,
        ];
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * @param PartialLLMResponse[] $partialResponses
     * @return LLMResponse
     */
    private function makeFromPartialResponses(array $partialResponses = []) : self {
        if (empty($partialResponses)) {
            return $this;
        }

        $content = '';
        $reasoningContent = '';
        foreach($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            $content .= $partialResponse->contentDelta;
            $reasoningContent .= $partialResponse->reasoningContentDelta;
            $this->responseData[] = $partialResponse->responseData;
            $this->usage()->accumulate($partialResponse->usage);
            $this->finishReason = $partialResponse->finishReason;
        }
        $this->content = $content;
        $this->reasoningContent = $reasoningContent;

        $tools = $this->makeTools($partialResponses);
        if (!empty($tools)) {
            $this->toolCalls = ToolCalls::fromArray($tools);
        }
        return $this;
    }

    private function makeTools(array $partialResponses): array {
        $tools = [];
        $currentTool = '';
        foreach ($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            if (('' !== ($partialResponse->toolName ?? ''))
                && ($currentTool !== ($partialResponse->toolName ?? ''))) {
                $currentTool = $partialResponse->toolName ?? '';
                $tools[$currentTool] = '';
            }
            if ('' !== $currentTool) {
                if (('' !== ($partialResponse->toolArgs ?? ''))) {
                    $tools[$currentTool] .= $partialResponse->toolArgs ?? '';
                }
            }
        }
        return $tools;
    }
}

```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Data/ToolCalls.php`:

```php
<?php
namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Utils\Json\Json;
use InvalidArgumentException;

class ToolCalls
{
    /**
     * @var ToolCall[]
     */
    private array $toolCalls;

    /**
     * @param ToolCall[] $toolCalls
     */
    public function __construct(array $toolCalls = []) {
        $this->toolCalls = $toolCalls;
    }

    public static function fromArray(array $toolCalls) : ToolCalls {
        $list = [];
        foreach ($toolCalls as $key => $toolCall) {
            $list[] = match(true) {
                is_array($toolCall) => ToolCall::fromArray($toolCall),
                is_object($toolCall) && $toolCall instanceof ToolCall => $toolCall,
                is_string($toolCall) => new ToolCall($key, $toolCall),
                default => throw new InvalidArgumentException('Cannot create ToolCall from provided data: ' . print_r($toolCall, true))
            };
        }
        return new ToolCalls($list);
    }

    public static function fromMapper(array $toolCalls, callable $mapper) : ToolCalls {
        $list = [];
        foreach ($toolCalls as $item) {
            $toolCall = $mapper($item);
            if ($toolCall instanceof ToolCall) {
                $list[] = $toolCall;
            }
        }
        return new ToolCalls($list);
    }

    public function count() : int {
        return count($this->toolCalls);
    }

    public function first() : ?ToolCall {
        return $this->toolCalls[0] ?? null;
    }

    public function last() : ?ToolCall {
        if (empty($this->toolCalls)) {
            return null;
        }
        return $this->toolCalls[count($this->toolCalls) - 1];
    }

    public function hasSingle() : bool {
        return count($this->toolCalls) === 1;
    }

    public function hasMany() : bool {
        return count($this->toolCalls) > 1;
    }

    public function hasNone() : bool {
        return empty($this->toolCalls);
    }

    public function hasAny() : bool {
        return !empty($this->toolCalls);
    }

    public function empty() : bool {
        return empty($this->toolCalls);
    }

    /**
     * @return ToolCall[]
     */
    public function all() : array {
        return $this->toolCalls;
    }

    public function reset() : void {
        $this->toolCalls = [];
    }

    public function add(string $toolName, string $args = '') : ToolCall {
        $newToolCall = new ToolCall(
            name: $toolName,
            args: $args
        );
        $this->toolCalls[] = $newToolCall;
        return $newToolCall;
    }

    public function updateLast(string $responseJson, string $defaultName) : ToolCall {
        $last = $this->last();
        if (empty($last)) {
            return $this->add($defaultName, $responseJson);
        }
        $last->withName($last->name() ?? $defaultName);
        $last->withArgs($responseJson);
        return $this->last();
    }

    public function finalizeLast(string $responseJson, string $defaultName) : ToolCall {
        return $this->updateLast($responseJson, $defaultName);
    }

    public function toArray() : array {
        $list = [];
        foreach ($this->toolCalls as $toolCall) {
            $list[] = $toolCall->toArray();
        }
        return $list;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/tmp/poly-tmp/LLM/Data/ToolCall.php`:

```php
<?php

namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Utils\Json\Json;

/**
 * Represents a tool call.
 */
class ToolCall
{
    private string $id;
    private string $name;
    private array $arguments;

    public function __construct(
        string $name,
        string|array $args = [],
        string $id = ''
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->arguments = match(true) {
            is_array($args) => $args,
            is_string($args) => Json::fromString($args)->toArray(),
            default => []
        };
    }

    public static function fromArray(array $toolCall) : ?ToolCall {
        if (empty($toolCall['name'])) {
            return null;
        }
        return new ToolCall(
            name: $toolCall['name'] ?? '',
            args: match(true) {
                is_array($toolCall['arguments'] ?? false) => $toolCall['arguments'],
                is_string($toolCall['arguments'] ?? false) => $toolCall['arguments'],
                is_null($toolCall['arguments'] ?? null) => [],
                default => throw new \InvalidArgumentException('ToolCall args must be a string or an array')
            },
            id: $toolCall['id'] ?? ''
        );
    }

    public function withId(string $id) : self {
        $this->id = $id;
        return $this;
    }

    public function withName(string $name) : self {
        $this->name = $name;
        return $this;
    }

    public function withArgs(string|array $args) : self {
        $this->arguments = match(true) {
            is_array($args) => $args,
            is_string($args) => Json::fromString($args)->toArray(),
            default => []
        };
        return $this;
    }

    public function hasArgs() : bool {
        return !empty($this->arguments);
    }

    public function id() : string {
        return $this->id;
    }

    public function name() : string {
        return $this->name;
    }

    public function args() : array {
        return $this->arguments;
    }

    public function argsAsJson() : string {
        return Json::encode($this->arguments);
    }

    public function hasValue(string $key) : bool {
        return isset($this->arguments[$key]);
    }

    public function value(string $key, mixed $default = null) : mixed {
        return $this->arguments[$key] ?? $default;
    }

    public function intValue(string $key, int $default = 0) : int {
        return (int) ($this->arguments[$key] ?? $default);
    }

    public function boolValue(string $key, bool $default = false) : bool {
        return (bool) ($this->arguments[$key] ?? $default);
    }

    public function stringValue(string $key, string $default = '') : string {
        return (string) ($this->arguments[$key] ?? $default);
    }

    public function arrayValue(string $key, array $default = []) : array {
        return (array) ($this->arguments[$key] ?? $default);
    }

    public function objectValue(string $key, ?object $default = null) : object {
        return (object) ($this->arguments[$key] ?? $default);
    }

    public function floatValue(string $key, float $default = 0.0) : float {
        return (float) ($this->arguments[$key] ?? $default);
    }

    public function toArray() : array {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }

    public function toToolCallArray() : array {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => Json::encode($this->arguments),
            ]
        ];
    }
}

```