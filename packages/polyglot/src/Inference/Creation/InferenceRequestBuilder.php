<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;

class InferenceRequestBuilder
{
    private ?Messages $messages;

    private ?string $model;

    private ?ToolDefinitions $tools;

    private ?ToolChoice $toolChoice;

    private ?ResponseFormat $responseFormat;

    private ?array $options;

    private ?ResponseCachePolicy $responseCachePolicy;

    private ?InferenceRetryPolicy $retryPolicy;

    private ?OperationCorrelation $telemetryCorrelation;

    private ?bool $streaming;

    private ?int $maxTokens;

    protected CachedInferenceContext $cachedContext;

    public function __construct(
        ?Messages $messages = null,
        ?string $model = null,
        ?ToolDefinitions $tools = null,
        ?ToolChoice $toolChoice = null,
        ?ResponseFormat $responseFormat = null,
        ?array $options = null,
        ?CachedInferenceContext $cachedContext = null,
        ?bool $streaming = null,
        ?int $maxTokens = null,
        ?ResponseCachePolicy $responseCachePolicy = null,
        ?InferenceRetryPolicy $retryPolicy = null,
        ?OperationCorrelation $telemetryCorrelation = null,
    ) {
        $this->messages = $messages;
        $this->model = $model;
        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
        $this->responseFormat = $responseFormat;
        $this->options = $options;
        $this->streaming = $streaming;
        $this->maxTokens = $maxTokens;
        $this->cachedContext = $cachedContext ?? new CachedInferenceContext;
        $this->responseCachePolicy = $responseCachePolicy;
        $this->retryPolicy = $retryPolicy;
        $this->telemetryCorrelation = $telemetryCorrelation;
    }

    /**
     * Sets the parameters for the inference request and returns the current instance.
     *
     * @param  Messages|null  $messages  The input messages for the inference.
     * @param  string  $model  The model to be used for the inference.
     * @param  ToolDefinitions|null  $tools  The tools to be used for the inference.
     * @param  ToolChoice|null  $toolChoice  The choice of tools for the inference.
     * @param  ResponseFormat|null  $responseFormat  The format of the response.
     * @param  array  $options  Additional options for the inference.
     */
    public function with(
        ?Messages $messages = null,
        ?string $model = null,
        ?ToolDefinitions $tools = null,
        ?ToolChoice $toolChoice = null,
        ?ResponseFormat $responseFormat = null,
        ?array $options = null,
    ): static {
        $this->messages = $messages ?? $this->messages;
        $this->model = $model ?? $this->model;
        $this->tools = $tools ?? $this->tools;
        $this->toolChoice = $toolChoice ?? $this->toolChoice;
        $this->responseFormat = $responseFormat ?? $this->responseFormat;
        $this->options = $options ?? $this->options;

        return $this;
    }

    public function withMessages(Messages $messages): static
    {
        $this->messages = $messages;

        return $this;
    }

    public function withModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function withTools(ToolDefinitions $tools): static
    {
        $this->tools = $tools;

        return $this;
    }

    public function withToolChoice(ToolChoice $toolChoice): static
    {
        $this->toolChoice = $toolChoice;

        return $this;
    }

    public function withResponseFormat(ResponseFormat $responseFormat): static
    {
        $this->responseFormat = $responseFormat;

        return $this;
    }

    public function withOptions(array $options): static
    {
        $this->options = array_merge($this->options ?? [], $options);

        return $this;
    }

    public function withStreaming(bool $stream = true): static
    {
        $this->streaming = $stream;

        return $this;
    }

    public function withMaxTokens(int $maxTokens): static
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function withResponseCachePolicy(ResponseCachePolicy $policy): static
    {
        $this->responseCachePolicy = $policy;

        return $this;
    }

    public function withRetryPolicy(InferenceRetryPolicy $retryPolicy): static
    {
        $this->retryPolicy = $retryPolicy;

        return $this;
    }

    public function withTelemetryCorrelation(?OperationCorrelation $telemetryCorrelation): static
    {
        $this->telemetryCorrelation = $telemetryCorrelation;

        return $this;
    }

    /**
     * Sets a cached context with provided messages, tools, tool choices, and response format.
     *
     * @param  Messages|null  $messages  Messages to be cached in the context.
     * @param  ToolDefinitions|null  $tools  Tools to be included in the cached context.
     * @param  ToolChoice|null  $toolChoice  Tool choices for the cached context.
     * @param  ResponseFormat|null  $responseFormat  Format for responses in the cached context.
     */
    public function withCachedContext(
        ?Messages $messages = null,
        ?ToolDefinitions $tools = null,
        ?ToolChoice $toolChoice = null,
        ?ResponseFormat $responseFormat = null,
    ): self {
        $this->cachedContext = new CachedInferenceContext(
            $messages ?? Messages::empty(),
            $tools ?? ToolDefinitions::empty(),
            $toolChoice ?? ToolChoice::empty(),
            $responseFormat ?? ResponseFormat::empty(),
        );

        return $this;
    }

    public function withRequest(InferenceRequest $request): self
    {
        $this->messages = $request->messages();
        $this->model = $request->model();
        $this->tools = $request->tools();
        $this->toolChoice = $request->toolChoice();
        $this->responseFormat = $request->responseFormat();
        $this->options = array_merge($this->options ?? [], $request->options());
        $this->streaming = $request->isStreamed();
        $this->cachedContext = $request->cachedContext();
        $this->responseCachePolicy = $request->responseCachePolicy();
        $this->retryPolicy = $request->retryPolicy();
        $this->telemetryCorrelation = $request->telemetryCorrelation();

        return $this;
    }

    public function create(): InferenceRequest
    {
        $options = $this->options ?? [];
        $options = $this->override($options, 'stream', $this->streaming);
        $options = $this->override($options, 'max_tokens', $this->maxTokens);

        return new InferenceRequest(
            messages: $this->messages,
            model: $this->model,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
            options: $options,
            cachedContext: $this->cachedContext,
            responseCachePolicy: $this->responseCachePolicy,
            retryPolicy: $this->retryPolicy,
            telemetryCorrelation: $this->telemetryCorrelation,
        );
    }

    private function override(array $source, string $key, mixed $value): array
    {
        if ($value === null) {
            return $source;
        }
        $source[$key] = $value;

        return $source;
    }
}
