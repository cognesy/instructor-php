<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Inference class is facade for handling inference requests and responses.
 */
class Inference
{
    use HandlesEvents;

    use Traits\HandlesLLMProvider;
    use Traits\HandlesRequestBuilder;

    /** @var HttpClient|null Facade-level HTTP client (optional) */
    protected ?HttpClient $httpClient = null;
    /** @var string|null Facade-level HTTP debug preset (optional) */
    protected ?string $httpDebugPreset = null;
    /** @var CanResolveLLMConfig|null Optional external config resolver */
    protected ?CanResolveLLMConfig $llmResolver = null;

    /** @var InferenceDriverFactory|null */
    private ?InferenceDriverFactory $inferenceFactory = null;

    /**
     * Constructor for initializing dependencies and configurations.
     */
    public function __construct(
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?CanProvideConfig $configProvider = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->requestBuilder = new InferenceRequestBuilder();
        $this->llmProvider = LLMProvider::new(
            $this->events,
            $configProvider,
        );
    }

    public static function registerDriver(string $name, string|callable $driver): void {
        InferenceDriverFactory::registerDriver($name, $driver);
    }

    // SHORTCUTS ///////////////////////////////////////////////////////////

    public function stream(): InferenceStream {
        return $this->create()->stream();
    }

    public function response(): InferenceResponse {
        return $this->create()->response();
    }

    // Shortcuts for creating responses in different formats

    public function get(): string {
        return $this->create()->get();
    }

    public function asJson(): string {
        return $this->create()->asJson();
    }

    public function asJsonData(): array {
        return $this->create()->asJsonData();
    }

    // INVOCATION //////////////////////////////////////////////////////////

    public function with(
        string|array $messages = [],
        string       $model = '',
        array        $tools = [],
        string|array $toolChoice = [],
        array        $responseFormat = [],
        array        $options = [],
        ?OutputMode  $mode = null,
    ) : static {
        $this->requestBuilder->withMessages($messages);
        $this->requestBuilder->withModel($model);
        $this->requestBuilder->withTools($tools);
        $this->requestBuilder->withToolChoice($toolChoice);
        $this->requestBuilder->withResponseFormat($responseFormat);
        $this->requestBuilder->withOptions($options);
        $this->requestBuilder->withOutputMode($mode);
        return $this;
    }

    public function withRequest(InferenceRequest $request): static {
        $this->requestBuilder->withRequest($request);
        return $this;
    }

    public function create(): PendingInference {
        $httpClient = $this->makeHttpClient();
        $inferenceDriver = $this->makeInferenceDriver($httpClient);
        $request = $this->requestBuilder->create();
        $execution = InferenceExecution::fromRequest($request);
        return new PendingInference(
            execution: $execution,
            driver: $inferenceDriver,
            eventDispatcher: $this->events,
        );
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    private function getInferenceFactory(): InferenceDriverFactory {
        return $this->inferenceFactory ??= new InferenceDriverFactory($this->events);
    }

    private function makeInferenceDriver(HttpClient $httpClient) : CanHandleInference {
        // Prefer explicit driver if provided via interface
        $resolver = $this->llmResolver ?? $this->llmProvider;
        if ($resolver instanceof HasExplicitInferenceDriver) {
            $explicit = $resolver->explicitInferenceDriver();
            if ($explicit !== null) {
                return $explicit;
            }
            return $this->getInferenceFactory()->makeDriver(
                config: $resolver->resolveConfig(),
                httpClient: $httpClient
            );
        }
        return $this->getInferenceFactory()->makeDriver(
            config: $resolver->resolveConfig(),
            httpClient: $httpClient
        );
    }

    private function makeHttpClient() : HttpClient {
        // Ensure HttpClient is available; build default if not provided
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }
        $builder = new HttpClientBuilder(events: $this->events);
        if ($this->httpDebugPreset !== null) {
            $builder = $builder->withDebugPreset($this->httpDebugPreset);
        }
        return $builder->create();
    }
}
