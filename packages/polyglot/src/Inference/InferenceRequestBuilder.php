<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\CachedContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class InferenceRequestBuilder
{
    private ?Messages $messages;
    private ?string $model;
    private ?array $tools;
    private null|string|array $toolChoice;
    private ?ResponseFormat $responseFormat;
    private ?array $options;
    private ?OutputMode $mode;

    private ?bool $streaming;
    private ?int $maxTokens;

    protected CachedContext $cachedContext;

    public function __construct(
        ?Messages $messages = null,
        ?string $model = null,
        ?array $tools = null,
        null|string|array $toolChoice = null,
        ?ResponseFormat $responseFormat = null,
        ?array $options = null,
        ?OutputMode $mode = null,
        ?CachedContext $cachedContext = null,
        ?bool $streaming = null,
        ?int $maxTokens = null,
    ) {
        $this->messages = $messages;
        $this->model = $model;
        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
        $this->responseFormat = $responseFormat;
        $this->options = $options;
        $this->mode = $mode;
        $this->streaming = $streaming;
        $this->maxTokens = $maxTokens;
        $this->cachedContext = $cachedContext ?? new CachedContext();
    }

    /**
     * Sets the parameters for the inference request and returns the current instance.
     *
     * @param string|array $messages The input messages for the inference.
     * @param string $model The model to be used for the inference.
     * @param array $tools The tools to be used for the inference.
     * @param string|array $toolChoice The choice of tools for the inference.
     * @param array $responseFormat The format of the response.
     * @param array $options Additional options for the inference.
     * @param OutputMode $mode The mode of operation for the inference.
     */
    public function with(
        null|string|array|Message|Messages $messages = null,
        ?string       $model = null,
        ?array        $tools = null,
        null|string|array $toolChoice = null,
        ?array        $responseFormat = null,
        ?array        $options = null,
        ?OutputMode  $mode = null,
    ) : static {
        $this->messages = $messages ? Messages::fromAny($messages) : $this->messages;
        $this->model = $model ?? $this->model;
        $this->tools = $tools ?? $this->tools;
        $this->toolChoice = $toolChoice ?? $this->toolChoice;
        $this->responseFormat = $responseFormat ? ResponseFormat::fromData($responseFormat) : $this->responseFormat;
        $this->options = $options ?? $this->options;
        $this->mode = $mode ?? $this->mode;
        return $this;
    }

    public function withMessages(string|array|Message|Messages $messages): static {
        $this->messages = Messages::fromAny($messages);
        return $this;
    }

    public function withModel(string $model): static {
        $this->model = $model;
        return $this;
    }

    public function withTools(array $tools): static {
        $this->tools = $tools;
        return $this;
    }

    public function withToolChoice(string|array $toolChoice): static {
        $this->toolChoice = $toolChoice;
        return $this;
    }

    public function withResponseFormat(array $responseFormat): static {
        $this->responseFormat = ResponseFormat::fromData($responseFormat);
        return $this;
    }

    public function withOptions(array $options): static {
        $this->options = array_merge($this->options ?? [], $options);
        return $this;
    }

    public function withStreaming(bool $stream = true): static {
        $this->streaming = $stream;
        return $this;
    }

    public function withMaxTokens(int $maxTokens): static {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function withOutputMode(?OutputMode $mode): static {
        $this->mode = $mode;
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
        $this->cachedContext = new CachedContext(
            $messages, $tools, $toolChoice, $responseFormat
        );
        return $this;
    }

    public function withRequest(InferenceRequest $request) : self {
        $this->messages = Messages::fromAny($request->messages());
        $this->model = $request->model();
        $this->tools = $request->tools();
        $this->toolChoice = $request->toolChoice();
        $this->responseFormat = $request->responseFormat();
        $this->options = array_merge($this->options ?? [], $request->options());
        $this->streaming = $request->isStreamed();
        $this->mode = $request->outputMode();
        $this->cachedContext = $request->cachedContext();
        return $this;
    }

    public function create(): InferenceRequest {
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
            mode: $this->mode,
            cachedContext: $this->cachedContext
        );
    }

    private function override(array $source, string $key, mixed $value): array {
        if ($value === null) {
            return $source;
        }
        $source[$key] = $value;
        return $source;
    }
}
