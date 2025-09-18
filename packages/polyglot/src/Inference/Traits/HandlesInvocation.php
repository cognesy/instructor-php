<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Traits;

use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\InferenceDriverFactory;
use Cognesy\Polyglot\Inference\PendingInference;

trait HandlesInvocation
{
    /** @var InferenceDriverFactory|null */
    private ?InferenceDriverFactory $inferenceFactory = null;

    private function getInferenceFactory(): InferenceDriverFactory {
        return $this->inferenceFactory ??= new InferenceDriverFactory($this->events);
    }
    public function withRequest(InferenceRequest $request): static {
        $this->requestBuilder->withRequest($request);
        return $this;
    }

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

    public function create(): PendingInference {
        $request = $this->requestBuilder->create();
        // Ensure HttpClient is available; build default if not provided
        if ($this->httpClient !== null) {
            $client = $this->httpClient;
        } else {
            $builder = new HttpClientBuilder(events: $this->events);
            if ($this->httpDebugPreset !== null) {
                $builder = $builder->withDebugPreset($this->httpDebugPreset);
            }
            $client = $builder->create();
        }

        // Prefer explicit driver if provided via interface
        $resolver = $this->llmResolver ?? $this->llmProvider;
        if ($resolver instanceof HasExplicitInferenceDriver) {
            $explicit = $resolver->explicitInferenceDriver();
            if ($explicit !== null) {
                $inferenceDriver = $explicit;
            } else {
                $config = $resolver->resolveConfig();
                $inferenceDriver = $this->getInferenceFactory()->makeDriver($config, $client);
            }
        } else {
            $config = $resolver->resolveConfig();
            $inferenceDriver = $this->getInferenceFactory()->makeDriver($config, $client);
        }
        return new PendingInference(
            request: $request,
            driver: $inferenceDriver,
            eventDispatcher: $this->events,
        );
    }
}
