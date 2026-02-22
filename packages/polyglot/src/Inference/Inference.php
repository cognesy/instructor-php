<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Pricing\StaticPricingResolver;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Inference class is facade for handling inference requests and responses.
 */
class Inference implements CanAcceptLLMConfig, CanCreateInference
{
    use HandlesEvents;

    use Traits\HandlesLLMProvider;
    use Traits\HandlesRequestBuilder;

    /** @var HttpClient|null Facade-level HTTP client (optional) */
    protected ?HttpClient $httpClient = null;
    /** @var string|null Facade-level HTTP debug preset (optional) */
    protected ?string $httpDebugPreset = null;

    /** @var InferenceDriverFactory|null */
    private ?InferenceDriverFactory $inferenceFactory = null;
    private ?int $inferenceFactoryEventBusId = null;
    private ?InferenceRuntime $runtimeCache = null;
    private bool $runtimeCacheDirty = true;

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
            $configProvider,
        );
    }

    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): static {
        $copy = clone $this;
        $copy->events = EventBusResolver::using($events);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    /**
     * @param string|callable(LLMConfig, HttpClient, EventDispatcherInterface): CanProcessInferenceRequest $driver
     */
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
        string|array|null $messages = null,
        ?string      $model = null,
        ?array       $tools = null,
        string|array|null $toolChoice = null,
        ?array       $responseFormat = null,
        ?array       $options = null,
        ?OutputMode  $mode = null,
    ) : static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->with(
            messages: $messages,
            model: $model,
            tools: $tools,
            toolChoice: $toolChoice,
            responseFormat: $responseFormat,
            options: $options,
            mode: $mode,
        );
        return $copy;
    }

    public function withRequest(InferenceRequest $request): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withRequest($request);
        return $copy;
    }

    public function create(?InferenceRequest $request = null): PendingInference {
        $request = $request ?? $this->requestBuilder->create();
        return $this->toRuntime()->create($request);
    }

    public function toRuntime(): InferenceRuntime {
        if (!$this->runtimeCacheDirty && $this->runtimeCache !== null) {
            return $this->runtimeCache;
        }

        $this->runtimeCache = $this->makeRuntime();
        $this->runtimeCacheDirty = false;
        return $this->runtimeCache;
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    private function makeRuntime(): InferenceRuntime {
        $resolver = $this->llmResolver ?? $this->llmProvider;
        $config = $resolver->resolveConfig();

        $httpClient = $this->makeHttpClient();
        $inferenceDriver = $this->makeInferenceDriver($httpClient, $resolver, $config);

        return new InferenceRuntime(
            driver: $inferenceDriver,
            events: $this->events,
            pricingResolver: new StaticPricingResolver($config->getPricing()),
        );
    }

    private function getInferenceFactory(): InferenceDriverFactory {
        $eventsId = spl_object_id($this->events);
        if ($this->inferenceFactory === null || $this->inferenceFactoryEventBusId !== $eventsId) {
            $this->inferenceFactory = new InferenceDriverFactory($this->events);
            $this->inferenceFactoryEventBusId = $eventsId;
        }
        return $this->inferenceFactory;
    }

    private function makeInferenceDriver(
        HttpClient $httpClient,
        CanResolveLLMConfig $resolver,
        LLMConfig $config,
    ) : CanProcessInferenceRequest {
        // Prefer explicit driver if provided via interface
        $explicit = $resolver instanceof HasExplicitInferenceDriver
            ? $resolver->explicitInferenceDriver()
            : null;

        if ($explicit !== null) {
            return $explicit;
        }

        return $this->getInferenceFactory()->makeDriver(
            config: $config,
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
            $builder = $builder->withHttpDebugPreset($this->httpDebugPreset);
        }
        return $builder->create();
    }

    protected function invalidateRuntimeCache(): void {
        $this->runtimeCache = null;
        $this->runtimeCacheDirty = true;
    }
}
